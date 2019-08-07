<?php

declare(strict_types = 1);

namespace SwooleProxy;

use function date;
use function posix_kill;
use function scandir;
use function strrchr;
use function trim;

/**
 * @codeCoverageIgnore
 */
class AutoReload
{

    /**
     * @var resource
     */
    protected $inotify;

    /**
     * Server main pid
     *
     * @var int
     */
    protected $pid;

    /**
     * Reload file type, default php
     *
     * @var string[]
     */
    protected $reloadFileTypes = ['.php' => true];

    /**
     * watched files
     *
     * @var string[]
     */
    protected $watchFiles = [];

    /**
     * Reload delay time in seconds
     *
     * @var int
     */
    protected $afterNSeconds = 1;

    /**
     * Current reload status
     *
     * @var bool
     */
    protected $reloading = false;

    /**
     * Inotify event
     *
     * @reference: https://www.php.net/manual/en/inotify.constants.php
     * @var int
     */
    protected $events;

    /**
     * Root directory
     *
     * @var string[]
     */
    protected $rootDirs = [];

    /**
     * Print info
     *
     * @param  string $log Information needs to print
     * @return void
     */
    private function putLog(string $log): void
    {
        echo '[' . date('Y-m-d H:i:s') . "]\t" . $log . "\n";
    }

    /**
     * Constructor
     *
     * @param int $serverPid server main pid
     * @throws \Exception
     */
    public function __construct(int $serverPid)
    {
        $this->pid = $serverPid;

        if (posix_kill($serverPid, 0) === false) {
            throw new \Exception(sprintf('Process #%d not found.', $serverPid));
        }

        $this->inotify = inotify_init();
        $this->events = IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE;
        swoole_event_add($this->inotify, function ($ifd): void {
            $events = inotify_read($this->inotify);

            if (!$events) {
                return;
            }

            foreach ($events as $ev) {
                if ($ev['mask'] === IN_IGNORED) {
                    continue;
                }

                if ($ev['mask'] === IN_CREATE
                    || $ev['mask'] === IN_DELETE
                    || $ev['mask'] === IN_MODIFY
                    || $ev['mask'] === IN_MOVED_TO
                    || $ev['mask'] === IN_MOVED_FROM) {
                    $fileType = strrchr($ev['name'], '.');

                    if (!isset($this->reloadFileTypes[$fileType])) {
                        continue;
                    }
                }

                // reloading, freeze 5 seconds
                if ($this->reloading) {
                    continue;
                }

                $this->putLog('after 1 second reload the server');

                swoole_timer_after($this->afterNSeconds * 1000, [$this, 'reload']);
                $this->reloading = true;
            }
        });
    }

    /**
     * Reload all workers
     *
     * @return void
     */
    public function reload(): void
    {
        $this->putLog('reloading');
        // Kill main thread
        posix_kill($this->pid, SIGUSR1);

        $this->clearWatch();

        // Watch again
        foreach ($this->rootDirs as $root) {
            $this->watch($root);
        }

        // Reset realod status
        $this->reloading = false;
    }

    /**
     * Add watching file types
     *
     * @param string $type file extension name with dot
     * @return void
     */
    public function addFileType(string $type): void
    {
        $type = trim($type, '.');
        $this->reloadFileTypes['.' . $type] = true;
    }

    /**
     * Clear all current watch
     *
     * @return void
     */
    private function clearWatch(): void
    {
        foreach ($this->watchFiles as $wd) {
            inotify_rm_watch($this->inotify, $wd);
        }

        $this->watchFiles = [];
    }

    /**
     * Start watch files
     *
     * @param string $dir Directory will watch
     * @param bool $root If the $dir is root directory
     * @throws \Exception
     * @return bool True if the watch starts. Returns false if watch is duplicated
     */
    public function watch(string $dir, bool $root = true): bool
    {
        if (!is_dir($dir)) {
            throw new \Exception(sprintf('[%s] is not a directory.', $dir));
        }

        // Avoid duplicate watch
        if (isset($this->watchFiles[$dir])) {
            return false;
        }

        if ($root) {
            $this->rootDirs[] = $dir;
        }

        $wd = inotify_add_watch($this->inotify, $dir, $this->events);
        $this->watchFiles[$dir] = $wd;

        $files = scandir($dir);

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }

            $path = $dir . '/' . $f;

            if (is_dir($path)) {
                $this->watch($path, false);
            }

            $fileType = strrchr($f, '.');

            if (!isset($this->reloadFileTypes[$fileType])) {
                continue;
            }

            $wd = inotify_add_watch($this->inotify, $path, $this->events);
            $this->watchFiles[$path] = $wd;
        }

        return true;
    }

    /**
     * Start waiting for event
     *
     * @return void
     */
    public function run(): void
    {
        swoole_event_wait();
    }

}
