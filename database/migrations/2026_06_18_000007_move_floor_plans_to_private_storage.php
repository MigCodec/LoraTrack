<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $this->move('public', 'local');
    }

    public function down(): void
    {
        $this->move('local', 'public');
    }

    private function move(string $from, string $to): void
    {
        DB::table('floor_plans')->where('disk', $from)->orderBy('id')->each(function (object $plan) use ($from, $to): void {
            $paths = array_values(array_filter([$plan->file_path, $plan->preview_path]));
            foreach ($paths as $path) {
                if (! Storage::disk($from)->exists($path)) {
                    throw new RuntimeException("No se encontró el archivo de plano [{$path}] en el disco [{$from}].");
                }
                if (! Storage::disk($to)->exists($path)) {
                    $stream = Storage::disk($from)->readStream($path);
                    if ($stream === false || ! Storage::disk($to)->writeStream($path, $stream)) {
                        throw new RuntimeException("No se pudo mover el plano [{$path}] al disco [{$to}].");
                    }
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            DB::table('floor_plans')->where('id', $plan->id)->update(['disk' => $to]);
            Storage::disk($from)->delete($paths);
        });
    }
};
