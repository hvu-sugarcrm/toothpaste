<?php

// Huy Vu
// hvu@sugarcrm.com

namespace Toothpaste\Sugar\Logic;
use Toothpaste\Sugar\Instance;
use Toothpaste\Sugar;
use MathieuViossat\Util\ArrayToTextTable;

class AnalyseStorage extends Sugar\BaseLogic
{
    protected $conn = null;
    protected $path = './upload';
    protected $detailed;
    protected $files_found, $total_files, $total_size;
    protected $notes, $emails, $kbs, $docs, $pics, $files, $summary;
    protected $timezone;
    protected $output_dir;

    public function __construct($dir, $timezone, $detailed = false) { 
        $this->detailed = $detailed;
        $this->total_files = 0;
        $this->total_size = 0;
        $this->files_found = 0;

        $this->notes = array();
        $this->emails = array();
        $this->kbs = array();
        $this->docs = array();
        $this->pics = array();
        $this->summary = array();

        //$this->files = array(); // This is enough but the order of the files will be random.
        $this->files = $this->generateArray(); // Therefore we need to generate a nice holder array
        $this->output_dir = ($dir) ? $dir : false;
        
        if ($timezone)
            date_default_timezone_set($timezone);
        else 
            date_default_timezone_set('Australia/Sydney');
    }

    // Getters

    protected function getConn() {
        if (empty($this->conn)) {
            $this->conn = \DBManagerFactory::getInstance()->getConnection();
        }

        return $this->conn;
    }
    protected function getNotes($year = null) { 
        return isset($year) ? $this->notes[$year] : $this->notes;
    }
    protected function getEmails($year = null) { 
        return isset($year) ? $this->emails[$year] : $this->emails;
    }
    protected function getKBs($year = null) { 
        return isset($year) ? $this->kbs[$year] : $this->kbs;
    }
    protected function getDocs($year = null) {
        return isset($year) ? $this->docs[$year] : $this->docs;
    }
    protected function getPics($year = null) {
        return isset($year) ? $this->pics[$year] : $this->pics;
    }
    protected function getFiles($year = null) {
        return isset($year) ? $this->files[$year] : $this->files;
    }
    public function getSummary() { 
        return $this->summary;
    }
    protected function getTotalFiles() {
        return $this->total_files;
    }
    protected function getTotalSize() { 
        return $this->total_size;
    }
    protected function getFilesFound() { 
        return $this->files_found;
    }
    protected function getOutputDir() {
        return $this->output_dir;
    }
    public function getPath() {
        return $this->addTrailingSlash($this->path);
    }
    
    // Setters
    protected function setTotalFiles($total_files) {
        $this->total_files = $total_files;
    }
    protected function setTotalSize($total_size) {
        $this->total_size = $total_size;
    }
    protected function setSummary($summary) {
        $this->summary = $summary;
    }


    /** 
    * Main function to perform the analysis
    */
    public function performStorageAnalysis() {
        $this->writeln('Analysising folder content ...');
        $this->writeln('');

        $total_size = 0;
        $total_files = 0;
        
        $directory = new \RecursiveDirectoryIterator($this->getPath(), \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS);
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
        $this->setTotalFiles($total_files);
        $this->setTotalSize($total_size);

        $this->writeln('Finished analysis, preparing report ...');
        $this->writeln('');
            
        if (is_dir($this->getOutputDir()))
            $this->generateReport(true);  // Save to disk
        else 
            $this->generateReport();    // Simply display everything on the terminal

        
    }

    /* 
     * Function to analyse the file record to see if it's a note,email and/or document
     * In Sugar files are associated with a Note. It can then be attached to an Email (as an attachment) or a KB article.
     * Other type of files are Document Revision and Avatar (for Users, Contacts)
     * 
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

        if ($this->detailed) {    
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
                if ($this->detailed) {
                    $this->writeln('The Note record is: ' . $this->getSugarURL() . '#Notes/' . $results[0]['id']);
                    $this->writeln('This file (' . $results[0]['name'] . ') is an attachment of the Email record: "' . $this->getSugarURL() . '#Emails/' . $results[0]['email_id']);
                }
                $this->notes[$year][$month][$day][$results[0]['id']] = array (
                    'note_link' => $this->getSugarURL() . '#Notes/' . $results[0]['id'],
                    'email_link' => $this->getSugarURL() . '#Emails/' . $results[0]['email_id'],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                $this->files[$year][$month][$day][$results[0]['id']] = array (
                    'type' => 'email',
                    'note_link' => $this->getSugarURL() . '#Notes/' . $results[0]['id'],
                    'email_link' => $this->getSugarURL() . '#Emails/' . $results[0]['email_id'],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                                
            } else {
                // This note is not associated to an email record 
                // If there's no parent_id then it's a standalone note, we will include its name, otherwise leave blank
                if ($this->detailed) {
                    $note = !isset($results[0]['parent_id']) ? $results[0]['name'] : '';
                    $this->writeln('This file is associated with the Note record:' . $note . ' - ' . $this->getSugarURL() . '#Notes/' . $results[0]['id']);
                }
                
                $this->notes[$year][$month][$day][$results[0]['id']] = array(
                    'note_link' => $this->getSugarURL() . '#Notes/' . $results[0]['id'],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                $this->files[$year][$month][$day][$results[0]['id']] = array (
                    'type' => 'note',
                    'note_link' => $this->getSugarURL() . '#Notes/' . $results[0]['id'],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );

                // Check if this note is connected with a KB record
                if(isset($results[0]['parent_type']) && strcmp($results[0]['parent_type'],'KBContents') == 0) {
                    if ($this->detailed) {
                        $this->writeln('This file (' . $results[0]['name'] . ') is an attachment of the KB record: "' . $this->getSugarURL() . '#KBContents/' . $results[0]['parent_id']);
                    }

                    $this->notes[$year][$month][$day][$results[0]['id']]['kb_link'] = $this->getSugarURL() . '#KBContents/' . $results[0]['parent_id'];
                    
                    $this->files[$year][$month][$day][$results[0]['id']]['type'] = 'kb';
                    $this->files[$year][$month][$day][$results[0]['id']]['kb_link'] = $this->getSugarURL() . '#KBContents/' . $results[0]['parent_id'];;

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
                    if ($this->detailed) {
                        $this->writeln('This file (' . $results[0]['filename'] . ') is a revision of the Document record: "' . $this->getSugarURL() . '#bwc/index.php?module=Documents&action=DetailView&record=' . $results[0]['document_id']);
                    }
                    $this->docs[$year][$month][$day][$results[0]['id']] = array(
                        'doc' => $results[0]['filename'],
                        'doc_link' => $this->getSugarURL() . "/#bwc/index.php?module=DocumentRevisions&action=DetailView&record=" . $results[0]['id'],
                        'date_modified' => $info->getMTime(),
                        'size' => $info->getSize(),
                        'path' => $info->getPathname()
                    );
                    $this->files[$year][$month][$day][$results[0]['id']] = array(
                        'type' => 'doc',
                        'doc' => $results[0]['filename'],
                        'doc_link' => $this->getSugarURL() . "/#bwc/index.php?module=DocumentRevisions&action=DetailView&record=" . $results[0]['id'],
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
            if ($this->detailed) {
                $this->writeln('This file is a picture of the Contact record: ' . $this->getSugarURL() . '#Contacts/' . $results[0]);
            }
            $this->pics[$year][$month][$day][$results[0]] = array(
                'pic' => $this->getSugarURL() . '#Contacts/' . $results[0],
                'date_modified' => $info->getMTime(),
                'size' => $info->getSize(),
                'path' => $info->getPathname()
            );
            $this->files[$year][$month][$day][$results[0]] = array(
                'type' => 'pic',
                'pic_link' => $this->getSugarURL() . '#Contacts/' . $results[0],
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
                if ($this->detailed) {
                    $this->writeln('This file is a picture of the User record: ' . $this->getSugarURL() . '#bwc/index.php?module=Users&action=DetailView&record=' . $result_pic[0]);
                }

                $this->pics[$year][$month][$day][$result_pic[0]] = array(
                    'pic' => $this->getSugarURL() . '#bwc/index.php?module=Users&action=DetailView&record=' . $result_pic[0],
                    'date_modified' => $info->getMTime(),
                    'size' => $info->getSize(),
                    'path' => $info->getPathname()
                );
                $this->files[$year][$month][$day][$result_pic[0]] = array(
                    'type' => 'pic',
                    'pic_link' => $this->getSugarURL() . '#bwc/index.php?module=Users&action=DetailView&record=' . $result_pic[0],
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
     * Generate a report 
     * @param $save_to_disk bool True to save to disk, otherwise print on terminal screen
     */
    protected function generateReport($save_to_disk = false) {
        $data = $details = '';
        $summary = array();

        if ($save_to_disk) {
            $this->createDir($this->addTrailingSlash($this->getOutputDir()));
            $file_name =  $this->addTrailingSlash($this->getOutputDir()) . 'analysis_result' . '_' . microtime(true);
            $this->writeln('Saving result to disk to ' . $file_name);
        }
        
        $data .= 'Processed ' . $this->getTotalFiles() . ' files with the TOTAL SIZE of ' . $this->prettify_filesize($this->getTotalSize()) . "\n";
        $data .= 'Found ' . $this->formatNumber($this->getFilesFound(), 0) . ' files associated with actual records.'. "\n";

        $filtered_array = $this->cleanUpArray($this->getFiles()); 

        if ($this->detailed)
            $details = "\n\nDETAILS BY YEAR:\n\n";
        
        foreach ($filtered_array as $year => $month_array) {
            $total_year_size = 0;
            $note_total = $email_total = $kb_total = $doc_total = $pic_total = 0;
            $details .= 'Files found in year ' . $year . ':' . "\n";            
                    
            foreach ($month_array as $month => $day_array) {
                $total_month_size = 0;
                $details .= '+ Month ' . $month . ':' . "\n";

                foreach ($day_array as $day => $values) {
                    $note_count_day = $email_count_day = $kb_count_day = $doc_count_day = $pic_count_day = 0;
                    $total_day_size = 0;
                    $file_count = count($values);

                    // Only do stuff if there's value in this day
                    if (!empty($values)) {
                        $details .= '  * Day ' . $day . ':' . "\n";

                        foreach ($values as $file_id => $file_info) {
                            if ($file_info) { 
                                $type = $file_info['type'];
                                $details .= '    - File:' . $file_info['path'] . ' - Size: ' . $this->prettify_filesize($file_info['size']) . ' - Date Modified: ' . date('r', $file_info['date_modified']) . "\n";
                                
                                if ($type == 'note') {
                                    $note_count_day++;
                                    $note_total++;
                                    $details .= '      NOTE: ' . $file_info['note_link'] . "\n";
                                } elseif ($type == 'email') {
                                    $email_count_day++;
                                    $email_total++;
                                    $details .= '      EMAIL: ' . $file_info['email_link'] . "\n";
                                } elseif ($type == 'kb') {
                                    $kb_count_day++;
                                    $kb_total++;
                                    $details .= '      KNOWLEDGEBASE: ' . $file_info['kb_link'] . "\n";
                                } elseif ($type == 'doc') {
                                    $doc_count_day++;
                                    $doc_total++;
                                    $details .= '      DOCUMENT: ' . $file_info['doc_link'] . "\n";
                                } elseif ($type == 'pic') {
                                    $pic_count_day++;
                                    $pic_total++;
                                    $details .= '      PICTURE of: ' . $file_info['pic_link'] . "\n";
                                }
                                $total_day_size += $file_info['size']; 
                            }
                        }
                    
                        $details .= "\n";
                        $details .= "      TOTAL FILES on this day: " . $file_count;
                        if ($note_count_day)
                            $details .= " (" . $note_count_day . " Note/s)";
                        if ($email_count_day)
                            $details .= " (" . $email_count_day . " Email/s)";
                        if ($kb_count_day) 
                            $details .= " (" . $kb_count_day . " Knowledge Base)";
                        if ($doc_count_day) 
                            $details .= " (" . $doc_count_day . " Document/s)";
                        if ($pic_count_day) 
                            $details .= " (" . $pic_count_day . " Picture/s)";

                        $details .= "\n";
                        $details .= "      TOTAL SIZE: " . $this->prettify_filesize($total_day_size);
                        $details .= "\n\n";

                    }  // End if 
                    $total_month_size += $total_day_size;


                } // End of day loop
                
                $total_year_size += $total_month_size;
                $details .= "  TOTAL SIZE of the month " . $month . ": " . $this->prettify_filesize($total_month_size) . "\n\n";
                
            }  // End of month loop
            $details .= "TOTAL SIZE of the year " . $year . ": " . $this->prettify_filesize($total_year_size) . "\n\n"; 

            $summary[$year]['year'] = $year;
            $summary[$year]['notes'] = $note_total;
            $summary[$year]['emails'] = $email_total;
            $summary[$year]['kbs'] = $kb_total;
            $summary[$year]['docs'] = $doc_total;
            $summary[$year]['pics'] = $pic_total;
            $summary[$year]['total'] = $note_total + $email_total + $kb_total + $doc_total + $pic_total;
            $summary[$year]['size'] = $this->prettify_filesize($total_year_size);


        } // End of year loop
        $this->setSummary($summary);

        if ($this->detailed)
            $data .= $details . "\n";

        $data .= "\n********************************************************************\n";
        $data .= "The biggest files are:  \n";
        $data .= $this->getBigFiles();
        $data .= "********************************************************************\n\n";

        $data .= $this->printSummary($this->getSummary());

        if ($save_to_disk) {
            file_put_contents($file_name, $data);
            $this->writeln('Saving to disk completed.');
        } else {
            echo $data;  
        }
    }

    /** 
     * Construct a holder array as the files won't be sorted nicely
     * @param $start_year int The year of the oldest file
     * @return $holder_array array The array [year][month][day] in descending order 
     */
    private function generateArray($start_year = null) {
        $oldest_file = $this->getOldestFile($this->getPath());
        $mtime = exec ('stat -c %Y '. $this->getPath() . $oldest_file);        

        $holder_array = array();
        $end_year = date('Y');
        $start = ($start_year) ? $start_year : date('Y', $mtime);
        
        for ($i=$end_year; $i>=$start;  $i--) {
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

    
    /** 
     * Helper function to print the data in a tabular format
     * @param $array array The array to be printed
     * @return string The information table
     */
    private function printSummary($array) {
        if ($array) {
            // Now print the summary in a tabular format
            $data = "SUMMARY  \n";

            $renderer = new ArrayToTextTable($array);
            $renderer->setDecorator(new \Zend\Text\Table\Decorator\Ascii());
            $renderer->setValuesAlignment(ArrayToTextTable::AlignCenter);

            return $data . $renderer->getTable();
        }
        return;
    }
    
}