<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitación a {{ $organizationName }}</title>
</head>
<body style="margin:0;background:#f1f5f9;color:#0f172a;font-family:Arial,Helvetica,sans-serif">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 12px">
        <tr><td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;overflow:hidden;border:1px solid #e2e8f0;border-radius:16px;background:#ffffff;box-shadow:0 8px 24px rgba(15,23,42,.08)">
                <tr><td style="background:{{ $primaryColor }};padding:28px 32px;color:{{ $primaryForeground }}">
                    <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase">LoraTrack</p>
                    <h1 style="margin:0;font-size:25px;line-height:1.25">Invitación para acceder a {{ $organizationName }}</h1>
                </td></tr>
                <tr><td style="padding:32px">
                    <p style="margin:0 0 18px;font-size:16px;line-height:1.65">Has sido invitado a acceder a la plataforma de búsqueda, localización y gestión de activos de <strong>{{ $organizationName }}</strong>.</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc">
                        <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px">Empresa</td><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:right;font-size:14px;font-weight:700">{{ $organizationName }}</td></tr>
                        <tr><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px">Administrador que invita</td><td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:right;font-size:14px;font-weight:700">{{ $administratorName }}</td></tr>
                        <tr><td style="padding:14px 16px;color:#64748b;font-size:13px">Grupo asignado</td><td style="padding:14px 16px;text-align:right;font-size:14px;font-weight:700">{{ $roleLabel }}</td></tr>
                        <tr><td style="padding:14px 16px;border-top:1px solid #e2e8f0;color:#64748b;font-size:13px">Vigencia del acceso</td><td style="padding:14px 16px;border-top:1px solid #e2e8f0;text-align:right;font-size:14px;font-weight:700">{{ $accessDuration }}</td></tr>
                    </table>
                    <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#475569">Para completar tu registro y establecer una contraseña segura, utiliza el siguiente botón:</p>
                    <p style="margin:0 0 24px;text-align:center"><a href="{{ $invitationUrl }}" style="display:inline-block;border-radius:9px;background:{{ $accentColor }};padding:14px 24px;color:{{ $accentForeground }};font-size:15px;font-weight:700;text-decoration:none">Aceptar invitación</a></p>
                    <p style="margin:0 0 8px;font-size:12px;line-height:1.55;color:#64748b">Este enlace es personal, vence el {{ $expiresAt }} y sólo puede utilizarse una vez.</p>
                    <p style="margin:0;font-size:12px;line-height:1.55;color:#64748b">Si no reconoces a esta empresa o al administrador indicado, o consideras que recibiste este mensaje por error, ignóralo. No se realizará ninguna acción sobre tu correo.</p>
                </td></tr>
                <tr><td style="border-top:1px solid #e2e8f0;background:{{ $secondaryColor }};padding:18px 32px;color:{{ $secondaryForeground }};font-size:11px;line-height:1.5">LoraTrack · Plataforma de inteligencia y localización de activos</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
