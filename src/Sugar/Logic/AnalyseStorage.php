<?php

// Huy Vu
// hvu@sugarcrm.com

namespace Toothpaste\Sugar\Logic;
use Toothpaste\Sugar\Instance;
use Toothpaste\Sugar;

class AnalyseStorage extends Sugar\BaseLogic
{
    protected $conn = null;
    protected $path = 'upload';
    protected $debug;
    protected $files_found, $total_files, $total_size;
    protected $docs, $pics, $notes, $files;
    protected $timezone;
    protected $output_dir;

    public function __construct($dir, $timezone, $debug = false) { 
        $this->debug = $debug;
        $this->total_files = 0;
        $this->total_size = 0;
        $this->files_found = 0;
        $this->docs = array();
        $this->pics = array();
        $this->notes = array();
        
        //$this->files = array(); // This is enough but the order of the files will be random.
        
        // We are going to construct a nice array for the files since 2010
        $this->files = $this->generateArray(2010);
        $this->output_dir = ($dir) ? $dir : '.';
        
        if ($timezone)
            date_default_timezone_set($timezone);
        else 
            date_default_timezone_set('Australia/Sydney');
    }

    protected function getConn() {
        if (empty($this->conn)) {
            $this->conn = \DBManagerFactory::getInstance()->getConnection();
        }

        return $this->conn;
    }

    // Getters
    protected function getTotalFiles() {
        return $this->total_files;
    }
    protected function getTotalSize() { 
        return $this->total_size;
    }
    protected function getNotes() { 
        return $this->notes;
    }
    protected function getPics() {
        return $this->pics;
    }
    protected function getDocs() {
        return $this->docs;
    }
    protected function getFiles() {
        return $this->files;
    }
    protected function getOutputDir() {
        return $this->output_dir;
    }

    // Setters
    protected function setTotalFiles($total_files) {
        $this->total_files = $total_files;
    }
    protected function setTotalSize($total_size) {
        $this->total_size = $total_size;
    }


    /** 
    * Main function to perform the analysis
    */
    public function performStorageAnalysis() {
        $this->writeln('Listing folder content ...');
        $this->writeln('');

        $total_size = 0;
        $total_files = 0;
        
        $directory = new \RecursiveDirectoryIterator('./' . $this->path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS);
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $info) {
            if ($info->isFile()) {
                if ($this->isGUID($info->getFilename())) {
                    // Analyse this file
                    $this->analyseRecord($info);
                    $this->writeln('********************************************************************'); 
                    
                    $total_size += $info->getSize();
                    $total_files++;
                }
            }
        }
        $this->writeln('Processed ' . $this->formatNumber($total_files, 0) . ' files with the total size of ' . $this->prettify_filesize($total_size));
        $this->writeln('Found ' . $this->formatNumber($this->files_found, 0) . ' files associated with actual records.');

        $this->setTotalFiles($total_files);
        $this->setTotalSize($total_size);

        if (is_dir($this->output_dir))
            $this->saveResultToDisk();
        else 
            $this->writeln('Corrupted output dir path');
    }

    /* 
     * Helper function to analyse the file record to see if it's a note,email and/or document
     * Using Last Modified time instead of Inode change time due to :
     * "Note: Note also that in some Unix texts the ctime of a file is referred to as being the creation time of the file. 
     *  This is wrong. There is no creation time for Unix files in most Unix filesystems." from  https://www.php.net/manual/en/function.filectime.php
     * @param   Obj SplFileInfo  $info  The file to check
     * @return  none
     */
    protected function analyseRecord($info) {
        $this->writeln('Checking file ' . $info->getPathname());
        $year = date('Y', $info->getMTime());
        $month = date('n', $info->getMTime());
        $day = date('j', $info->getMTime());

        if ($this->debug) {    
            $this->writeln('Date modified: ' . date('c', $info->getMTime()));
            $this->writeln('Size: '. $this->prettify_filesize($info->getSize()));
        }
       
        $query = $this->getConn()->createQueryBuilder();
        $expr = $query->expr();

        // Checking for note records
		$query
            ->select('id', 'name', 'parent_type', 'parent_id', 'email_type', 'email_id')
            ->from('notes')
            ->where($expr->eq('id', ':id'))
            ->setParameter('id', $info->getFilename());

        $results =  $query->execute()->fetchAll(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);

        if(isset($results[0]['id'])) {
            if (isset($results[0]['email_id'])) {
                // This note is associated to an email record
                if ($this->debug) {
                    $this->writeln('The Note record is: ' . $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Notes/' . $results[0]['id']);
                    $this->writeln('This file (' . $results[0]['name'] . ') is an attachment of the Email record: "' . $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Emails/' . $results[0]['email_id']);
                }
                $this->notes[$year][$month][$day][$results[0]['id']] = array (
                    'note_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Notes/' . $results[0]['id'],
                    'email_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Emails/' . $results[0]['email_id'],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                $this->files[$year][$month][$day][$results[0]['id']] = array (
                    'type' => 'email',
                    'note_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Notes/' . $results[0]['id'],
                    'email_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Emails/' . $results[0]['email_id'],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                                
            } else {
                // This note is not associated to an email record 
                // If there's no parent_id then it's a standalone note, we will include its name, otherwise leave blank
                if ($this->debug) {
                    $note = !isset($results[0]['parent_id']) ? $results[0]['name'] : '';
                    $this->writeln('This file is associated with the Note record:' . $note . ' - ' . $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Notes/' . $results[0]['id']);
                }
                
                $this->notes[$year][$month][$day][$results[0]['id']] = array(
                    'note_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Notes/' . $results[0]['id'],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                $this->files[$year][$month][$day][$results[0]['id']] = array (
                    'type' => 'note',
                    'note_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Notes/' . $results[0]['id'],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );

                // Check if this note is connected with a KB record
                if(isset($results[0]['parent_type']) && strcmp($results[0]['parent_type'],'KBContents') == 0) {
                    if ($this->debug) {
                        $this->writeln('This file (' . $results[0]['name'] . ') is an attachment of the KB record: "' . $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#KBContents/' . $results[0]['parent_id']);
                    }

                    $this->notes[$year][$month][$day][$results[0]['id']]['kb_link'] = $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#KBContents/' . $results[0]['parent_id'];
                    
                    $this->files[$year][$month][$day][$results[0]['id']]['type'] = 'kb';
                    $this->files[$year][$month][$day][$results[0]['id']]['kb_link'] = $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#KBContents/' . $results[0]['parent_id'];;

                }
            }
            $this->files_found++;
            return;
        } else { 
            // Not a note record so we're checking for document records

            $query->resetQueryParts(['select','from']);

            $query
            ->select('id', 'document_id, filename')
            ->from('document_revisions')
            ->where($expr->eq('id', ':id'))
            ->setParameter('id', $info->getFilename());
        
            $results = $query->execute()->fetchAll(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);

            if(isset($results[0]['id'])) {
                if (isset($results[0]['document_id'])) {
                    // This is associated to a document record
                    if ($this->debug) {
                        $this->writeln('This file (' . $results[0]['filename'] . ') is a revision of the Document record: "' . $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#bwc/index.php?module=Documents&action=DetailView&record=' . $results[0]['document_id']);
                    }
                    $this->docs[$year][$month][$day][$results[0]['id']] = array(
                        'doc' => $results[0]['filename'],
                        'doc_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . "/#bwc/index.php?module=DocumentRevisions&action=DetailView&record=" . $results[0]['id'],
                        'date_modified' => $info->getMTime(),
                        'size' => $info->getSize(),
                        'path' => $info->getPathname()
                    );
                    $this->files[$year][$month][$day][$results[0]['id']] = array(
                        'type' => 'doc',
                        'doc' => $results[0]['filename'],
                        'doc_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . "/#bwc/index.php?module=DocumentRevisions&action=DetailView&record=" . $results[0]['id'],
                        'date_modified' => $info->getMTime(),
                        'size' => $info->getSize(),
                        'path' => $info->getPathname()
                    );
                } 
                $this->files_found++;
                return;
            } else {
                // At this stage it's none of the above, so we're checking for pictures
                // Passing date values to save time on function calls
                $this->checkPicture($info, $year, $month, $day);
            }
        }
    }

    /* 
     * Helper function to indetify whether the file is a picture of a contact/ user record
     * 
     * @param   Obj SplFileInfo  $info  The file to check
     * @return  none
     */
    private function checkPicture($info, $year, $month, $day) {
        $query = $this->getConn()->createQueryBuilder();
        $expr = $query->expr();

        // Checking for picture of contacts
		$query
            ->select('id')
            ->from('contacts')
            ->where($expr->eq('picture', ':picture'))
            ->setParameter('picture', $info->getFilename());

        $results =  $query->execute()->fetchAll(\Doctrine\DBAL\FetchMode::COLUMN);

        if(isset($results[0])) {
            // This is associated to a contact record
            if ($this->debug) {
                $this->writeln('This file is a picture of the Contact record: ' . $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Contacts/' . $results[0]);
            }
            $this->pics[$year][$month][$day][$results[0]] = array(
                'pic' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Contacts/' . $results[0],
                'date_modified' => $info->getMTime(),
                'size' => $info->getSize(),
                'path' => $info->getPathname()
            );
            $this->files[$year][$month][$day][$results[0]] = array(
                'type' => 'pic',
                'pic_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#Contacts/' . $results[0],
                'date_modified' => $info->getMTime(),
                'size' => $info->getSize(),
                'path' => $info->getPathname()
            );

            $this->files_found++;
        } else { 
            // Checking for a user picture
            $query->resetQueryPart('from');
            $query->from('users');
            $result_pic =  $query->execute()->fetchAll(\Doctrine\DBAL\FetchMode::COLUMN);

            if (isset($result_pic[0])) { 
                if ($this->debug) {
                    $this->writeln('This file is a picture of the User record: ' . $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#bwc/index.php?module=Users&action=DetailView&record=' . $result_pic[0]);
                }

                $this->pics[$year][$month][$day][$result_pic[0]] = array(
                    'pic' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#bwc/index.php?module=Users&action=DetailView&record=' . $result_pic[0],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                $this->files[$year][$month][$day][$result_pic[0]] = array(
                    'type' => 'pic',
                    'pic_link' => $this->addTrailingSlash($GLOBALS['sugar_config']['site_url']) . '#bwc/index.php?module=Users&action=DetailView&record=' . $result_pic[0],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                $this->files_found++;
            }
        }
        return;
    }

    /** 
     * Print the entire files to a report saved on the disk
     */
    protected function saveResultToDisk() {
        $this->createDir($this->addTrailingSlash($this->getOutputDir()));
        $data = '';

        $file_name =  $this->addTrailingSlash($this->getOutputDir()) . 'analysis_result' . '_' . microtime(true);

        $this->writeln('Saving result to disk to ' . $file_name);

        $data .= 'Processed ' . $this->getTotalFiles() . ' files with the TOTAL SIZE of ' . $this->prettify_filesize($this->getTotalSize()) . "\n";
        $data .= 'Found ' . $this->formatNumber($this->files_found, 0) . ' files associated with actual records.'. "\n";

        $filtered_array = $this->cleanUpArray($this->getFiles()); 

        foreach ($filtered_array as $year => $month_array) {

            $data .= 'Files found in year ' . $year . ':' . "\n";            
            $total_year_size = 0;
                    
            foreach ($month_array as $month => $day_array) {
                
                $data .= '+ Month ' . $month . ':' . "\n";
                $total_month_size = 0;
                
                foreach ($day_array as $day => $values) {

                    $kb_count = $doc_count = $note_count = $pic_count = $email_count = 0;
                    $total_day_size = 0;
                    $file_count = count($values);

                    if (!empty($values)) {
                        $data .= '  * Day ' . $day . ':' . "\n";

                        foreach ($values as $key => $detail) {
                            if ($detail) { 
                                $type = $detail['type'];
                                $data .= '    - File:' . $detail['path'] . ' - Size: ' . $this->prettify_filesize($detail['size']) . ' - Date Modified: ' . date('r', $detail['date_modified']) . "\n";
                                
                                if ($type == 'note') {
                                    $note_count++;
                                    $data .= '      Link to NOTE: ' . $detail['note_link'] . "\n";
                                } elseif ($type == 'email') {
                                    $email_count++;
                                    $data .= '      Link to EMAIL: ' . $detail['email_link'] . "\n";
                                } elseif ($type == 'kb') {
                                    $data .= '      Link to KB: ' . $detail['kb_link'] . "\n";
                                    $kb_count++;
                                } elseif ($type == 'doc') {
                                    $data .= '      Link to DOCUMENT: ' . $detail['doc_link'] . "\n";
                                    $doc_count++;
                                } elseif ($type == 'pic') {
                                    $data .= '      Link to record with the PICTURE: ' . $detail['pic_link'] . "\n";
                                    $pic_count++;
                                }
                                $total_day_size += $detail['size'];   
                            }
                        }
                    
                        $data .= "\n";
                        $data .= "      TOTAL FILES on this day: " . $file_count;
                        if ($note_count)
                            $data .= " (" . $note_count . " Note/s)";
                        if ($email_count)
                            $data .= " (" . $email_count . " Email/s)";
                        if ($kb_count) 
                            $data .= " (" . $kb_count . " Knowledge Base)";
                        if ($doc_count) 
                            $data .= " (" . $doc_count . " Document/s)";
                        if ($pic_count) 
                            $data .= " (" . $pic_count . " Picture/s)";

                        $data .= "\n";
                        $data .= "      TOTAL SIZE: " . $this->prettify_filesize($total_day_size);
                        $data .= "\n\n";
                    } 
                    $total_month_size += $total_day_size;
                }
                $total_year_size += $total_month_size;
                $data .= "  TOTAL SIZE of the month " . $month . ": " . $this->prettify_filesize($total_month_size) . "\n\n";
                
            } 
            $data .= "TOTAL SIZE of the year " . $year . ": " . $this->prettify_filesize($total_year_size) . "\n\n"; 
        }

        $data .= "\n";

        if ($this->debug) {
            echo $data;  
        } else {
            file_put_contents($file_name, $data);
            $this->writeln('Saving to disk completed.');
        }
    }

    /** 
     * Construct a holder array as the files won't be sorted nicely
     * @param $start_year int The year of the oldest file
     * @return $holder_array array The array [year][month][day] in descending order 
     */
    private function generateArray($start_year) {
        $holder_array = array();
        $end_year = date('Y');
        
        for ($i=$end_year; $i>=$start_year;  $i--) {
            for ($j=12; $j>0; $j--) {
                for ($k=31; $k>0; $k--) {
                    $holder_array[$i][$j][$k] = array();
                }
            }
        }
        return $holder_array;
    }

    /** 
     * Helper function to remove empty array item 
     * It will traverse the file list and pick out the one with value 
     * 
     * @param $array array The array to be cleaned up
     * @return $array_to_keep array The array with values only
     */
    private function cleanUpArray($array) { 
        $array_to_keep = array();

        foreach ($array as $year => $month_array) {
            foreach($month_array as $month => $day_array) { 
                foreach($day_array as $day => $value) { 
                    if (!empty($value)) {
                        $array_to_keep[$year][$month][$day] = $value;
                    }   
                }
            }
        }
        return $array_to_keep;
    }
}