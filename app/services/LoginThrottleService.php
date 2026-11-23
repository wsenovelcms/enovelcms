<?php

class LoginThrottleService
{

    private $windowSeconds = 600;

    private $maxFailures = 3;

    private $lockSeconds = 1800;

    private $logFile;

    private $lockFile;

    public function __construct(string $namespace = 'admin', string $subject = '')
    {
        if ($namespace === 'admin') {
            $dir = ROOT_PATH . 'data/admin/';
        } else {
            $safeNs = preg_replace('/[^a-zA-Z0-9_\-]/', '', $namespace) ?: 'misc';
            $dir = ROOT_PATH . 'data/throttle/' . $safeNs . '/';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $subject = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $subject);
        $prefix = $subject !== '' ? $subject . '.' : '';
        $this->logFile = $dir . $prefix . 'login.log.php';
        $this->lockFile = $dir . $prefix . 'login_lock.php';
        if (!file_exists($this->logFile)) {
            $this->atomicWrite($this->logFile, "<?php exit; ?>\n");
        }
    }

    public function isLocked(): bool
    {
        $lock = $this->readLock();
        if (!is_array($lock) || empty($lock['until'])) {
            return false;
        }
        if (time() < (int)$lock['until']) {
            return true;
        }
        @unlink($this->lockFile);
        return false;
    }

    public function secondsUntilUnlock(): int
    {
        $lock = $this->readLock();
        if (!is_array($lock) || empty($lock['until'])) {
            return 0;
        }
        $remaining = (int)$lock['until'] - time();
        return $remaining > 0 ? $remaining : 0;
    }

    private function readLock(): ?array
    {
        if (!file_exists($this->lockFile)) return null;
        $raw = (string)@file_get_contents($this->lockFile);
        $guard = "<?php exit; ?>\n";
        if (strncmp($raw, $guard, strlen($guard)) === 0) $raw = substr($raw, strlen($guard));
        $lock = json_decode($raw, true);
        return is_array($lock) ? $lock : null;
    }

    public function recordAttempt(bool $success): void
    {
        $entry = array(
            'time' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'success' => $success,
        );
        $this->atomicWrite($this->logFile, json_encode($entry) . "\n", true);

        if ($success) {

            if (file_exists($this->lockFile)) {
                @unlink($this->lockFile);
            }
            return;
        }

        $failures = $this->countRecentFailures();
        if ($failures >= $this->maxFailures) {
            $lockData = array(
                'until' => time() + $this->lockSeconds,
                'message' => 'Too many login failures',
                'failures' => $failures,
            );
            $this->atomicWrite($this->lockFile, json_encode($lockData));
        }
    }

    private function countRecentFailures(): int
    {
        if (!file_exists($this->logFile)) {
            return 0;
        }
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return 0;
        }
        $cutoff = time() - $this->windowSeconds;
        $failures = 0;
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $log = json_decode($lines[$i], true);
            if (!is_array($log) || !isset($log['time'])) {
                continue;
            }
            $ts = strtotime($log['time']);
            if ($ts === false || $ts <= $cutoff || !empty($log['success'])) {
                break;
            }
            $failures++;
        }
        return $failures;
    }

    private function atomicWrite($path, $content, $append = false): void
    {
        if ($append) {
            file_put_contents($path, $content, LOCK_EX | FILE_APPEND);
        } else {
            file_put_contents($path, "<?php exit; ?>\n" . $content, LOCK_EX);
        }
    }
}
