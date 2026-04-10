<?php

namespace App\Services\Students;

use App\Models\User;
use Illuminate\Support\Str;

class BulkStudentParser
{
    public const MAX_ROWS = 45;

    public function parse(User $teacher, string $raw): BulkParseResult
    {
        $raw = ltrim($raw, "\xEF\xBB\xBF"); // strip BOM
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));

        if (count($lines) === 0) {
            return new BulkParseResult();
        }

        // Detect delimiter from header line
        $header = $lines[0];
        $delimiter = str_contains($header, ';') ? ';' : ',';

        // Drop header if it looks like a header
        if (Str::contains(Str::lower($header), ['nome', 'email'])) {
            array_shift($lines);
        }

        if (count($lines) > self::MAX_ROWS) {
            throw new BulkLimitExceededException();
        }

        $subjectsByName = $teacher->subjectsAsTeacher()
            ->get()
            ->keyBy(fn ($s) => Str::lower(trim($s->name)));

        $existingEmails = User::whereIn('email', $this->collectEmails($lines, $delimiter))
            ->pluck('email')
            ->map(fn ($e) => Str::lower($e))
            ->flip();

        $valid = [];
        $invalidFormat = [];
        $invalidSubject = [];
        $duplicateInBatch = [];
        $emailExists = [];
        $seenInBatch = [];

        foreach ($lines as $idx => $line) {
            $lineNo = $idx + 2; // header counts as line 1
            if (trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line, $delimiter);
            if (count($cols) < 3) {
                $invalidFormat[] = ['line' => $lineNo, 'reason' => 'Colunas insuficientes'];
                continue;
            }

            $name = trim($cols[0] ?? '');
            $email = Str::lower(trim($cols[1] ?? ''));
            $subjectName = trim($cols[2] ?? '');

            if ($name === '' || strlen($name) < 2) {
                $invalidFormat[] = ['line' => $lineNo, 'reason' => 'Nome inválido'];
                continue;
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidFormat[] = ['line' => $lineNo, 'reason' => 'E-mail inválido'];
                continue;
            }

            $subject = $subjectsByName->get(Str::lower($subjectName));
            if (! $subject) {
                $invalidSubject[] = [
                    'line' => $lineNo,
                    'email' => $email,
                    'subject_name' => $subjectName,
                ];
                continue;
            }

            if (isset($seenInBatch[$email])) {
                $duplicateInBatch[] = ['line' => $lineNo, 'email' => $email];
                continue;
            }
            $seenInBatch[$email] = true;

            if ($existingEmails->has($email)) {
                $emailExists[] = ['line' => $lineNo, 'email' => $email];
                continue;
            }

            $valid[] = [
                'line' => $lineNo,
                'name' => $name,
                'email' => $email,
                'subject_id' => $subject->id,
            ];
        }

        return new BulkParseResult(
            valid: $valid,
            invalidFormat: $invalidFormat,
            invalidSubject: $invalidSubject,
            duplicateInBatch: $duplicateInBatch,
            emailExists: $emailExists,
        );
    }

    private function collectEmails(array $lines, string $delimiter): array
    {
        $emails = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line, $delimiter);
            if (isset($cols[1])) {
                $emails[] = Str::lower(trim($cols[1]));
            }
        }
        return array_filter($emails);
    }
}
