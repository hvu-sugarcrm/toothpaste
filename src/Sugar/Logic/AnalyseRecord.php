<?php

// Huy Vu
// hvu@sugarcrm.com

namespace Toothpaste\Sugar\Logic;
use Toothpaste\Sugar\Instance;
use Toothpaste\Sugar;
use MathieuViossat\Util\ArrayToTextTable;


class AnalyseRecord extends Sugar\BaseLogic
{
    protected $months;

    public function __construct($months = null) {
        $this->months = ($months)? $months : 6;
    }

    public function performRecordAnalysis()
    {
        $results = [];
        $db = \DBManagerFactory::getInstance();

        $query = $db->getConnection()->createQueryBuilder();
        $expr = $query->expr();

        // TABLE_ROWS
        // The number of rows. Some storage engines, such as MyISAM, store the exact count. 
        // For other storage engines, such as InnoDB, this value is an approximation, and may vary from the actual value by as much as 40% to 50%. 
        // In such cases, use SELECT COUNT(*) to obtain an accurate count.
        // https://dev.mysql.com/doc/refman/5.6/en/tables-table.html
        $query
            ->select("table_name tables,
            concat(round(table_rows/1000000,2),'Mil') rows,
            concat(round(data_length/(1024*1024*1024),2),'G') data_size,
            concat(round(index_length/(1024*1024*1024),2),'G') index_size,
            concat(round((data_length+index_length)/(1024*1024*1024),2),'G') total_size,
            round(index_length/data_length,2) index_data_ratio")
            ->from('information_schema.TABLES')
            ->where($expr->eq('table_schema', ':db_name'))
            ->setParameter('db_name', $GLOBALS['sugar_config']['dbconfig']['db_name'])
            
            ->orderBy('round(data_length+index_length)', 'DESC')
            ->setMaxResults(10);

        $results =  $query->execute()->fetchAll(\Doctrine\DBAL\FetchMode::ASSOCIATIVE);
        
        // Print summary
        $this->writeln('');
        $this->writeln('10 biggest tables (approx.):');
        $renderer = new ArrayToTextTable($results);
        $renderer->setDecorator(new \Zend\Text\Table\Decorator\Ascii());
        $this->writeln($renderer->getTable());


        // Print details
        $query2 = $db->getConnection()->createQueryBuilder();
        $sm = $db->getConnection()->getSchemaManager();

        $month_array = $this->getMonthArray($this->months); 
    
        foreach ($month_array as $key => $months) {
            foreach ($results as $values) {
                $has_range = false;
                $query2->resetQueryParts(['select','from', 'where']);
                $table = $sm->listTableDetails($values['tables']);
    
                $count_field = ($table->hasColumn('id')) ? 'id' : '*';
                $query2->select('COUNT(' . $count_field . ') as count');
    
                $query2->from($values['tables']);
                $total = $query2->execute()->fetchColumn();

                if ($table->hasColumn('date_entered') || $table->hasColumn('date_created')) {
                    if ($table->hasColumn('date_entered')) {
                        $query2->where('date_entered BETWEEN :start_date AND :end_date');
                        $column = 'date_entered';
                    } elseif ($table->hasColumn('date_created')) {
                        $query2->where('date_created BETWEEN :start_date AND :end_date');
                        $column = 'date_created';
                    }
                } else {
                    if ($table->hasColumn('date_modified')) {
                        $query2->where('date_modified BETWEEN :start_date AND :end_date');
                        $column = 'date_modified';
                    }
                }
                            
                if ($query2->getQueryPart('where'))
                    $has_range = true;
    
                $query2
                    ->setParameter('start_date', $months['start_date'])
                    ->setParameter('end_date', $months['end_date']);
                
                //$this->writeln($query2->getSQL());
                $array[$key][] = array(
                    'table' => $values['tables'], 
                    'count' => ($has_range) ? $query2->execute()->fetchColumn() : 'n/a',
                    'DB total' => $total
                    //'column' => ($has_range) ? $column : '',
                    //'start_date' => ($has_range) ? $months['start_date'] : '',
                    //'end_date' => ($has_range) ? $months['end_date'] : '',
                );
                
            }  
        }
        
        foreach ($array as $key => $values) {
            $this->writeln('Record Count in ' . $key);
            $renderer = new ArrayToTextTable($array[$key]);
            $renderer->setDecorator(new \Zend\Text\Table\Decorator\Ascii());
            $this->writeln($renderer->getTable());
        }
    }

    /** 
     * Helper function to get an array of the last x months
     * For the purpose of this exercise, it is assumed that the month starts with 01 and ends with 31
     */
    private function getMonthArray($months) {
        $month_array = array();
        
        for ($i=1; $i<=$months; $i++) {
            // Using the 1st of the month to ensure sub returns the corret timestamp for the previous months
            $present_date = new \DateTime(date('Y') . '-' . date('m') . '-01'); 
            $timestamp = $present_date->sub(new \DateInterval('P' . $i . 'M'));

            $month = $timestamp->format('m');
            $year = $timestamp->format('Y');

            $month_array[$year.'-'.$month]['start_date'] = $year . '-' . $month . '-01';
            $month_array[$year.'-'.$month]['end_date'] = $year . '-' . $month . '-31';
        }

        return $month_array;
    }
}
