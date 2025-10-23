<?php

namespace App\Service\Logging;

use Monolog\Formatter\HtmlFormatter;
use Monolog\LogRecord;

class ErrorEmailFormatter extends HtmlFormatter
{
    public function format(LogRecord $record): string
    {
        $levelValue = $record['level']->value ?? 0;
        $output = $this->addTitle($record['level_name'], $levelValue);
        $output .= '<table cellspacing="1" width="100%" class="monolog-output">';

        // Extract web and user information from extra
        $webInfo = $record['extra']['web'] ?? null;
        $userInfo = $record['extra']['user'] ?? null;

        // Display URL and HTTP information prominently
        if ($webInfo) {
            $output .= $this->addSectionHeader('Request Information');

            if (isset($webInfo['url'])) {
                $output .= $this->addRow('URL', $webInfo['url']);
            }

            if (isset($webInfo['http_method'])) {
                $output .= $this->addRow('Method', $webInfo['http_method']);
            }

            if (isset($webInfo['ip'])) {
                $output .= $this->addRow('IP Address', $webInfo['ip']);
            }

            if (isset($webInfo['referrer']) && !empty($webInfo['referrer'])) {
                $output .= $this->addRow('Referrer', $webInfo['referrer']);
            }
        }

        // Display user information prominently
        if ($userInfo) {
            $output .= $this->addSectionHeader('User Information');

            if (isset($userInfo['authenticated']) && $userInfo['authenticated']) {
                $output .= $this->addRow('Status', '<strong style="color: #2e7d32;">Authenticated</strong>', false);

                if (isset($userInfo['username'])) {
                    $output .= $this->addRow('Username', $userInfo['username']);
                }

                if (isset($userInfo['email'])) {
                    $output .= $this->addRow('Email', $userInfo['email']);
                }

                if (isset($userInfo['full_name'])) {
                    $output .= $this->addRow('Full Name', $userInfo['full_name']);
                }

                if (isset($userInfo['roles'])) {
                    $output .= $this->addRow('Roles', implode(', ', $userInfo['roles']));
                }
            } else {
                $output .= $this->addRow('Status', '<strong style="color: #d32f2f;">Anonymous</strong>', false);
            }
        }

        // Error details
        $output .= $this->addSectionHeader('Error Details');
        $output .= $this->addRow('Message', (string) $record['message']);
        $output .= $this->addRow('Time', $record['datetime']->format('Y-m-d H:i:s T'));
        $output .= $this->addRow('Channel', $record['channel']);
        $output .= $this->addRow('Level', $record['level_name']);

        if (!empty($record['context'])) {
            $embeddedTable = '<table cellspacing="1" width="100%">';
            foreach ($record['context'] as $key => $value) {
                $embeddedTable .= $this->addRow($key, $this->formatValue($value));
            }
            $embeddedTable .= '</table>';
            $output .= $this->addRow('Context', $embeddedTable, false);
        }

        // Display remaining extra data (excluding web and user as they're shown above)
        $extraData = array_diff_key($record['extra'], ['web' => null, 'user' => null]);
        if (!empty($extraData)) {
            $output .= $this->addSectionHeader('Additional Information');
            $embeddedTable = '<table cellspacing="1" width="100%">';
            foreach ($extraData as $key => $value) {
                $embeddedTable .= $this->addRow($key, $this->formatValue($value));
            }
            $embeddedTable .= '</table>';
            $output .= $this->addRow('Extra Data', $embeddedTable, false);
        }

        return $output . '</table>';
    }

    protected function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return '<pre>' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES, 'UTF-8') . '</pre>';
        }

        if (is_object($value)) {
            if ($value instanceof \Throwable) {
                return sprintf(
                    '<strong>%s</strong>: %s<br><br><strong>File:</strong> %s:%d<br><br><strong>Trace:</strong><pre>%s</pre>',
                    htmlspecialchars(get_class($value), ENT_NOQUOTES, 'UTF-8'),
                    htmlspecialchars($value->getMessage(), ENT_NOQUOTES, 'UTF-8'),
                    htmlspecialchars($value->getFile(), ENT_NOQUOTES, 'UTF-8'),
                    $value->getLine(),
                    htmlspecialchars($value->getTraceAsString(), ENT_NOQUOTES, 'UTF-8')
                );
            }

            if (method_exists($value, '__toString')) {
                return htmlspecialchars((string) $value, ENT_NOQUOTES, 'UTF-8');
            }

            return '<pre>' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_NOQUOTES, 'UTF-8') . '</pre>';
        }

        return htmlspecialchars((string) $value, ENT_NOQUOTES, 'UTF-8');
    }

    protected function addTitle(string $title, mixed $level): string
    {
        $title = htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8');
        $levelValue = is_int($level) ? $level : 0;
        $levelColor = $this->getLevelColor($levelValue);

        return "<h1 style=\"background: $levelColor;color: #ffffff;padding: 5px;\">$title</h1>";
    }

    protected function addSectionHeader(string $title): string
    {
        $title = htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8');

        return "<tr style=\"padding: 4px;text-align: left;\">\n<th colspan=\"2\" style=\"background: #1976D2;color: #ffffff;padding: 8px;font-size: 14px;text-align: left;\">$title</th>\n</tr>";
    }

    protected function addRow(string $th, mixed $td = ' ', bool $escapeTd = true): string
    {
        $th = htmlspecialchars($th, ENT_NOQUOTES, 'UTF-8');
        $tdString = is_string($td) ? $td : (string) $td;

        if ($escapeTd) {
            $tdString = '<pre>' . htmlspecialchars($tdString, ENT_NOQUOTES, 'UTF-8') . '</pre>';
        }

        return "<tr style=\"padding: 4px;text-align: left;\">\n<th style=\"vertical-align: top;background: #ccc;color: #000\" width=\"100px\">$th:</th>\n<td style=\"padding: 4px;text-align: left;vertical-align: top;background: #eee;color: #000\">" . $tdString . "</td>\n</tr>";
    }

    protected function getLevelColor(mixed $level): string
    {
        $levelInt = is_int($level) ? $level : 0;

        return match (true) {
            $levelInt >= 500 => '#D32F2F', // Critical/Alert/Emergency - Dark red
            $levelInt >= 400 => '#F57C00', // Error - Orange
            $levelInt >= 300 => '#FBC02D', // Warning - Yellow
            $levelInt >= 250 => '#1976D2', // Notice - Blue
            $levelInt >= 200 => '#388E3C', // Info - Green
            default => '#757575', // Debug - Grey
        };
    }
}

