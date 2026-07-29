<?php

declare(strict_types=1);

$path = $argv[1] ?? '';

if ($path === '' || ! is_file($path)) {
    fwrite(STDOUT, "::warning title=PHPUnit report unavailable::No JUnit report was produced.\n");

    exit(0);
}

$document = new DOMDocument();

if (! @$document->load($path)) {
    fwrite(STDOUT, "::warning title=PHPUnit report unreadable::The JUnit report could not be parsed.\n");

    exit(0);
}

foreach ($document->getElementsByTagName('testcase') as $testcase) {
    $label = trim($testcase->getAttribute('classname').'::'.$testcase->getAttribute('name'));
    $file = trim($testcase->getAttribute('file'));
    $line = trim($testcase->getAttribute('line'));

    foreach (['failure', 'error'] as $kind) {
        foreach ($testcase->getElementsByTagName($kind) as $failure) {
            $message = trim($failure->getAttribute('message').' '.trim((string) $failure->textContent));
            $properties = ['title=PHPUnit '.$kind.': '.escape($label)];

            if ($file !== '') {
                $properties[] = 'file='.escape($file);
            }

            if ($line !== '' && ctype_digit($line)) {
                $properties[] = 'line='.$line;
            }

            fwrite(STDOUT, '::error '.implode(',', $properties).'::'.escape(truncate($message))."\n");
        }
    }
}

function escape(string $value): string
{
    return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
}

function truncate(string $value): string
{
    return mb_strimwidth($value, 0, 1500, '...');
}
