#!/usr/bin/env python3
"""
Build professional, repository-visible HTML/PDF documentation from docs/.

The script generates stable consumer-facing files under docs/. For PDF output it can use one
of these optional engines:

- WeasyPrint:  pip install markdown weasyprint
- Playwright:  pip install markdown playwright && python -m playwright install chromium
- Pandoc:      install pandoc and a PDF engine, or use it for HTML conversion

Examples:

  python tools/build_docs_pdf.py
  python tools/build_docs_pdf.py --version 1.0 --engine weasyprint
  python tools/build_docs_pdf.py --no-pdf
"""

from __future__ import annotations

import argparse
import html
import os
import re
import shutil
import subprocess
import sys
import tempfile
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable


ROOT = Path(__file__).resolve().parents[1]
DOCS = ROOT / "docs"
OUTPUT = DOCS

TECHNICAL_DOCUMENTS = [
    "docs/user-guide.md",
    "docs/engineering/executive-technical-summary.md",
    "docs/engineering/architecture.md",
    "docs/engineering/domain-and-data-model.md",
    "docs/engineering/telemetry-and-positioning.md",
    "docs/engineering/integrations.md",
    "docs/engineering/api-contracts.md",
    "docs/engineering/security-and-identity.md",
    "docs/operations/operations-runbook.md",
    "docs/operations/field-commissioning.md",
    "docs/integrations/tti.md",
    "docs/integrations/sap.md",
]

DEPLOYMENT_DOCUMENTS = [
    "docs/operations/dependency-matrix.md",
    "docs/operations/deployment-and-environments.md",
    "docs/operations/deployment-ubuntu-lts.md",
    "docs/operations/deployment-windows-iis.md",
    "docs/operations/sql-server.md",
    "docs/operations/compliance-baseline.md",
    "docs/operations/operations-runbook.md",
    "docs/operations/field-commissioning.md",
]

PUBLICATIONS = [
    ("LoraTrack-Technical-Documentation", "Technical Documentation and User Guide", TECHNICAL_DOCUMENTS),
    ("LoraTrack-Deployment-Guide", "Professional Deployment and Operations Guide", DEPLOYMENT_DOCUMENTS),
]


@dataclass(frozen=True)
class BuildMeta:
    version: str


def slugify(value: str) -> str:
    value = re.sub(r"[^A-Za-z0-9]+", "-", value.strip().lower())
    return value.strip("-") or "section"


def read_documents(paths: Iterable[str]) -> list[tuple[Path, str]]:
    documents: list[tuple[Path, str]] = []
    for item in paths:
        path = ROOT / item
        if path.exists():
            documents.append((path, path.read_text(encoding="utf-8")))
        else:
            print(f"warning: missing document skipped: {item}", file=sys.stderr)
    return documents


def normalize_markdown(path: Path, markdown: str) -> str:
    relative = path.relative_to(ROOT).as_posix()
    title = title_from_markdown(markdown) or relative
    body = markdown.strip()

    if body.startswith("# "):
        body = re.sub(r"^#\s+(.+)$", r"# \1", body, count=1, flags=re.MULTILINE)
    else:
        body = f"# {title}\n\n{body}"

    return "\n\n".join(
        [
            f'<a id="{slugify(relative)}"></a>',
            body,
        ]
    )


def title_from_markdown(markdown: str) -> str | None:
    for line in markdown.splitlines():
        if line.startswith("# "):
            return line[2:].strip()
    return None


def build_combined_markdown(documents: list[tuple[Path, str]], meta: BuildMeta, title: str) -> str:
    toc_items = []
    sections = []
    for path, content in documents:
        relative = path.relative_to(ROOT).as_posix()
        document_title = title_from_markdown(content) or relative
        toc_items.append(f"- [{document_title}](#{slugify(relative)})")
        sections.append(normalize_markdown(path, content))

    title_page = f"""
<section class="cover">
<h1>LoraTrack</h1>
<h2>{html.escape(title)}</h2>
<p><strong>Document version:</strong> {html.escape(meta.version)}</p>
<p><strong>Classification:</strong> Public product documentation</p>
</section>

<div class="page-break"></div>

# Document Control

| Field | Value |
| --- | --- |
| Product | LoraTrack |
| Document type | {title} |
| Document version | {meta.version} |
| Audience | Users, administrators, engineering, operations, and security teams |

> This documentation describes product capabilities and procedures. References to practices or standards do not constitute certification, independent assurance, or formal customer acceptance.

# Document Index

{chr(10).join(toc_items)}

<div class="page-break"></div>
"""

    return title_page.strip() + "\n\n" + "\n\n<div class=\"page-break\"></div>\n\n".join(sections)


def markdown_to_html(markdown: str) -> str:
    try:
        import markdown as markdown_module  # type: ignore

        return markdown_module.markdown(
            markdown,
            extensions=[
                "extra",
                "tables",
                "toc",
                "fenced_code",
                "sane_lists",
            ],
            output_format="html5",
        )
    except Exception:
        pass

    pandoc = shutil.which("pandoc")
    if pandoc:
        with tempfile.TemporaryDirectory() as tmp:
            input_path = Path(tmp) / "input.md"
            output_path = Path(tmp) / "output.html"
            input_path.write_text(markdown, encoding="utf-8")
            subprocess.check_call(
                [pandoc, str(input_path), "--from", "markdown+pipe_tables", "--to", "html5", "-o", str(output_path)]
            )
            return output_path.read_text(encoding="utf-8")

    return fallback_markdown_to_html(markdown)


def fallback_markdown_to_html(markdown: str) -> str:
    blocks: list[str] = []
    in_code = False
    code_lines: list[str] = []
    paragraph: list[str] = []

    def flush_paragraph() -> None:
        if paragraph:
            blocks.append("<p>" + html.escape(" ".join(paragraph)) + "</p>")
            paragraph.clear()

    for raw_line in markdown.splitlines():
        line = raw_line.rstrip()
        if line.startswith("```"):
            if in_code:
                blocks.append("<pre><code>" + html.escape("\n".join(code_lines)) + "</code></pre>")
                code_lines.clear()
                in_code = False
            else:
                flush_paragraph()
                in_code = True
            continue
        if in_code:
            code_lines.append(line)
            continue
        if not line.strip():
            flush_paragraph()
            continue
        if line.startswith("#"):
            flush_paragraph()
            level = min(len(line) - len(line.lstrip("#")), 6)
            text = line[level:].strip()
            blocks.append(f"<h{level}>{html.escape(text)}</h{level}>")
            continue
        if line.startswith("- "):
            flush_paragraph()
            blocks.append("<ul><li>" + html.escape(line[2:].strip()) + "</li></ul>")
            continue
        paragraph.append(line)

    flush_paragraph()
    if code_lines:
        blocks.append("<pre><code>" + html.escape("\n".join(code_lines)) + "</code></pre>")
    return "\n".join(blocks)


def css() -> str:
    return """
@page {
  size: A4;
  margin: 18mm 16mm 18mm 16mm;
  @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 8pt; color: #64748b; }
  @bottom-left { content: "LoraTrack - Technical Documentation"; font-size: 8pt; color: #64748b; }
}
* { box-sizing: border-box; }
body {
  margin: 0;
  color: #172033;
  font-family: "Segoe UI", "Inter", "Arial", sans-serif;
  font-size: 10.5pt;
  line-height: 1.55;
}
h1, h2, h3, h4 { color: #0f2f4a; line-height: 1.2; page-break-after: avoid; }
h1 { font-size: 24pt; border-bottom: 2px solid #1f6f8b; padding-bottom: 6pt; margin-top: 0; }
h2 { font-size: 17pt; margin-top: 22pt; }
h3 { font-size: 13pt; margin-top: 16pt; }
h4 { font-size: 11pt; }
a { color: #0b6684; text-decoration: none; }
code {
  color: #1e293b;
  background: #eef4f7;
  border: 1px solid #d9e5ea;
  border-radius: 3px;
  padding: 1px 3px;
  font-family: "Cascadia Mono", "Consolas", monospace;
  font-size: 9pt;
}
pre {
  background: #0f172a;
  color: #e2e8f0;
  border-radius: 6px;
  padding: 10pt;
  overflow-wrap: break-word;
  white-space: pre-wrap;
  page-break-inside: avoid;
}
pre code { color: inherit; background: transparent; border: 0; padding: 0; }
table { width: 100%; border-collapse: collapse; margin: 10pt 0 14pt; page-break-inside: avoid; }
th {
  background: #e6f0f4;
  color: #0f2f4a;
  text-align: left;
  font-weight: 700;
}
th, td { border: 1px solid #cbd8df; padding: 6pt; vertical-align: top; }
blockquote {
  margin: 12pt 0;
  padding: 8pt 10pt;
  border-left: 4px solid #1f6f8b;
  background: #f3f8fa;
}
ul, ol { padding-left: 18pt; }
li { margin: 2pt 0; }
.cover {
  min-height: 240mm;
  display: flex;
  flex-direction: column;
  justify-content: center;
  border-left: 8pt solid #1f6f8b;
  padding-left: 22pt;
}
.cover h1 {
  font-size: 42pt;
  border: 0;
  margin-bottom: 4pt;
}
.cover h2 {
  font-size: 18pt;
  color: #375569;
  margin-top: 0;
}
.doc-source {
  color: #64748b;
  font-size: 8.5pt;
  margin: 0 0 10pt;
}
.page-break { page-break-before: always; break-before: page; }
"""


def build_html(body: str, meta: BuildMeta, title: str) -> str:
    return f"""<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="generator" content="tools/build_docs_pdf.py">
  <meta name="doc-version" content="{html.escape(meta.version)}">
  <title>LoraTrack - {html.escape(title)}</title>
  <style>{css()}</style>
</head>
<body>
{body}
</body>
</html>
"""


def render_pdf_with_weasyprint(html_path: Path, pdf_path: Path) -> None:
    from weasyprint import HTML  # type: ignore

    HTML(filename=str(html_path)).write_pdf(str(pdf_path))


def render_pdf_with_playwright(html_path: Path, pdf_path: Path) -> None:
    from playwright.sync_api import sync_playwright  # type: ignore

    with sync_playwright() as playwright:
        edge = Path(os.environ.get("PROGRAMFILES(X86)", "")) / "Microsoft/Edge/Application/msedge.exe"
        browser = playwright.chromium.launch(executable_path=str(edge) if edge.exists() else None)
        page = browser.new_page()
        page.goto(html_path.as_uri(), wait_until="networkidle")
        page.pdf(
            path=str(pdf_path),
            format="A4",
            print_background=True,
            margin={"top": "18mm", "right": "16mm", "bottom": "18mm", "left": "16mm"},
        )
        browser.close()


def render_pdf_with_pandoc(markdown_path: Path, pdf_path: Path, meta: BuildMeta) -> None:
    pandoc = shutil.which("pandoc")
    if not pandoc:
        raise RuntimeError("pandoc not found")
    subprocess.check_call(
        [
            pandoc,
            str(markdown_path),
            "-o",
            str(pdf_path),
            "--toc",
            "--metadata",
            f"title=LoraTrack - Technical Documentation {meta.version}",
            "--metadata",
            "lang=es",
        ]
    )


def generate_pdf(engine: str, html_path: Path, markdown_path: Path, pdf_path: Path, meta: BuildMeta) -> str:
    errors: list[str] = []
    engines = ["weasyprint", "playwright", "pandoc"] if engine == "auto" else [engine]

    for selected in engines:
        try:
            if selected == "weasyprint":
                render_pdf_with_weasyprint(html_path, pdf_path)
            elif selected == "playwright":
                render_pdf_with_playwright(html_path, pdf_path)
            elif selected == "pandoc":
                render_pdf_with_pandoc(markdown_path, pdf_path, meta)
            else:
                raise RuntimeError(f"unknown engine: {selected}")
            return selected
        except Exception as exc:
            errors.append(f"{selected}: {exc}")

    raise RuntimeError(
        "No PDF engine succeeded. Install one of: "
        "`pip install markdown weasyprint`, "
        "`pip install markdown playwright && python -m playwright install chromium`, "
        "or `pandoc` with a PDF engine. Errors: "
        + " | ".join(errors)
    )


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Build public LoraTrack documentation HTML/PDF.")
    parser.add_argument("--version", default="1.0", help="Public document version; never derived from Git metadata.")
    parser.add_argument("--output-dir", default=str(OUTPUT), help="Output directory; defaults to docs/.")
    parser.add_argument("--engine", choices=["auto", "weasyprint", "playwright", "pandoc"], default="auto", help="PDF engine.")
    parser.add_argument("--no-pdf", action="store_true", help="Only generate Markdown/HTML/manifest.")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    output_dir = Path(args.output_dir).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)

    meta = BuildMeta(version=args.version)

    for basename, title, document_paths in PUBLICATIONS:
        documents = read_documents(document_paths)
        if not documents:
            print(f"error: no documentation files found for {basename}", file=sys.stderr)
            return 1

        markdown_path = output_dir / f"{basename}.md"
        html_path = output_dir / f"{basename}.html"
        pdf_path = output_dir / f"{basename}.pdf"
        combined_markdown = build_combined_markdown(documents, meta, title)
        markdown_path.write_text(combined_markdown, encoding="utf-8")
        html_body = markdown_to_html(combined_markdown)
        html_path.write_text(build_html(html_body, meta, title), encoding="utf-8")

        if not args.no_pdf:
            engine = generate_pdf(args.engine, html_path, markdown_path, pdf_path, meta)
            print(f"pdf generated with {engine}: {pdf_path}")
        else:
            print(f"pdf skipped for {basename}")

        print(f"markdown: {markdown_path}")
        print(f"html: {html_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
