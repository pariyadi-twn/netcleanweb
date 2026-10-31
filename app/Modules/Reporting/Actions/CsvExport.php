<?php

namespace App\Modules\Reporting\Actions;

final class CsvExport
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function build(array $headers, iterable $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, array_map($this->sanitize(...), $headers), escape: '\\');
        foreach ($rows as $row) {
            fputcsv($stream, array_map($this->sanitize(...), $row), escape: '\\');
        }
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    public function sanitize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
