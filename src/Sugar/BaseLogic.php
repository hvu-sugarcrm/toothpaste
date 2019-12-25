<?php

// Enrico Simonetti
// enricosimonetti.com

namespace Toothpaste\Sugar;

class BaseLogic
{
    protected $logger;

    public function setLogger($out = null)
    {
        if ($out) {
            $this->logger = $out; 
        }
    }

    public function writeln($message)
    {
        if ($this->logger) {
            $this->logger->writeln($message);
        }
    }

    public function write($message)
    {
        if ($this->logger) {
            $this->logger->write($message);
        }
    }

    public function addTrailingSlash($string) : String
    {
        return rtrim($string, '/') . '/';
    }

    protected function createDir($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    protected function formatNumber(float $number, ?int $decimals = 2) : string
    {
        return number_format($number, $decimals, '.', ',');
    }

    protected function matchesAllPatterns(String $string, array $patternsToMatch = [], array $patternsToIgnore = []) : bool
    {
        // if it does not match any of the required patterns, return false
        if (!empty($patternsToMatch)) {
            foreach ($patternsToMatch as $have) {
                if (!preg_match($have, $string)) {
                    return false;
                }
            }
        }

        // if it matches any of the patterns it should not match, return false
        if (!empty($patternsToIgnore)) {
            foreach ($patternsToIgnore as $notHave) {
                if (preg_match($notHave, $string)) {
                    return false;
                }
            }
        }

        // if it has not yet returned false, return true
        return true;
    }

    protected function findFiles(String $dir, array $patternsToMatch = [], array $patternsToIgnore = []) : array
    {
        $files = [];
        if (is_dir($dir)) {
            $rdi = new \RecursiveDirectoryIterator($dir);
            foreach (new \RecursiveIteratorIterator($rdi) as $f) {
                $filePath = $f->getPathName();
                if ($this->matchesAllPatterns($filePath, $patternsToMatch, $patternsToIgnore)) {
                    $files[] = $filePath;
                }
            }
        }
        return $files;
    }

    /* 
     * Helper function to indetify whether the file has a GUID format
     * https://gist.github.com/Joel-James/3a6201861f12a7acf4f2
     * @param   string  $string   The string to check
     * @return  boolean
     */
    protected function isGUID($string) {
        if (!is_string($string) || (preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/', $string) !== 1)) {
            return false;
        }
        return true;
    }

    /* 
     * Helper function to print the file size in a human readable format
     * https://gist.github.com/liunian/9338301
     * @param   int  $bytes   The size in bytes
     * @return  string 
     */
    protected function prettify_filesize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }

    protected function getSugarURL() { 
        return $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']);
    }

    /** 
     * Helper function to get the list of the largest files 
     * @param $num int The number of files, default to 20
     * @return string The result of the command
     */
    protected function getBigFiles($num = 20) {
        return shell_exec('ls -hsS ' . $this->getPath() .' | head -' . $num);
    }

    protected function getOldestFile($dir) {
        return shell_exec('ls -t ' . $this->getPath() .' | tail -1');
    }

}
