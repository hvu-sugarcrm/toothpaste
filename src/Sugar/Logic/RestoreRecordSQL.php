<?php

// Huy Vu
// hvu@sugarcrm.com

namespace Toothpaste\Sugar\Logic;
use Toothpaste\Sugar\Instance;
use Toothpaste\Sugar;

class RestoreRecordSQL extends Sugar\BaseLogic
{
    protected $module_name = '';
    protected $record_id = '';
    protected $db_backup = '';

    protected $conn = null;
    protected $conn_backup = null;
    protected $debug = false;
    protected $skip_modules = array(
        'created_by_link', 
        'modified_user_link', 
        'following_link', 
        'team_link', 
        'team_count_link', 
        'teams', 
        'assigned_user_link', 
        'activities', 
        'members', 
        'member_of', 
        'business_centers',
        'archived_emails',
        'campaign_accounts'
    );

    public function __construct($module_name, $record_id, $db_backup) { 
        $this->db_backup = $db_backup;
        $this->module_name = $module_name;
        $this->record_id = $record_id;
    }

    public function printSQL() {
        if (empty($this->module_name) || empty($this->record_id)) {
            $this->writeln('To be able to restore a record, the module name and a record id are required');
        }
        if (empty($this->db_backup)) {
            $this->writeln('To be able to restore all relationships, the config name of the backup db is required');
        }


        $this->writeln('Printing the SQL to restore: "' . $this->module_name . '" record with id: "' . $this->record_id);
        $this->writeln('');

        //$bean = \BeanFactory::retrieveBean($this->module_name, $this->record_id, ['deleted' => 0]);
        $bean = \BeanFactory::retrieveBean($this->module_name, $this->record_id, array('disable_row_level_security' => true), false);

        if (!empty($bean->id)) {

            if ($bean->deleted) {
                
                //$mainBean->mark_undeleted($mainBean->id);

                $query = $this->getConn()->createQueryBuilder();
                $expr = $query->expr();
                $query
                    ->update($bean->table_name)
                    ->set('deleted', 0)
                    ->where($expr->eq('id',  ':id'))
                    ->setParameter('id', $this->record_id);

                $this->writeln('### Query to restore the main record: ###');
                $this->writeln($this->makeSQL($query));
            }

            $this->writeln('### Query to restore the email addresses: ###');
            $this->restoreEmailAddress($bean->date_modified);

            $linked_fields = $bean->get_linked_fields();

            foreach($linked_fields as $link_name => $properties) {
                /* 
                // $bean: Accounts
                
                $properties = {
                    name: "contacts",
                    type: "link",
                    relationship: "accounts_contacts",
                    module: "Contacts",
                    bean_name: "Contact",
                    source: "non-db",
                    vname: "LBL_CONTACTS"
                }
                // $bean: Contacts:
                $properties = {
                    name: "accounts",
                    type: "link",
                    relationship: "accounts_contacts",
                    link_type: "one",
                    source: "non-db",
                    vname: "LBL_ACCOUNT",
                    duplicate_merge: "disabled",
                    primary_only: "1"
                    }
                */
			
                if (!in_array($link_name, $this->skip_modules)) {
                    $this->restoreDeletedRelationshipRecords($link_name, $properties, $bean);
                } 
            }
				
        } else {
            $this->writeln('The provided record does not exist');
        }

        $this->writeln('');
    }

    protected function getConn() {
        if (empty($this->conn)) {
            $this->conn = \DBManagerFactory::getInstance()->getConnection();
        }

        return $this->conn;
    }

    protected function getConnBk() {
        if (empty($this->conn_backup)) {
            $this->conn_backup = \DBManagerFactory::getInstance($this->db_backup)->getConnection();
        }

        return $this->conn_backup;
    }
    
    private function makeSQL($query, $module = null, $record_count = 0) {
		$sql = $query->getSQL();
		$comment = '';

		if ($module) {
            $comment = '### Query to restore ' . $module . ' (' . $record_count . ' to be restored) ###';
            $this->writeln($comment);

		}
		foreach ($query->getParameters() as $key => $value) {
			if (is_array($value)) {
				$string = "'" . implode("','", $value) . "'";
			} else {
				$string = "'" . $value . "'";
			}
			$sql = str_replace(':'.$key, $string, $sql);
		}

		return $sql . ';';
	}

    private function restoreEmailAddress($date_modified) {
		$query = $this->getConn()->createQueryBuilder();
		$expr = $query->expr();
		$query
			->update('email_addr_bean_rel')
			->set('deleted', 0)
			->where($expr->eq('bean_id',  ':id'))
			->andWhere($expr->eq('date_modified', ':date_modified'))
			->setParameters(['id' => $this->record_id, 'date_modified' => $date_modified]);

		$this->writeln($this->makeSQL($query));
    }
    

    private function restoreDeletedRelationshipRecords($link_name, $properties, $bean)
    {
		$linkObj = null;
		if (empty($link_name)) return false;

		if ($this->debug) {
			$this->writeln('Checking  ' . $link_name);
		}
		
		$module = isset($properties['module']) ? $properties['module'] : $properties['relationship'];

		if (isset($properties['relationship'])) {
			// Get all the fields of the deleted parent contact
			$fieldDefs = $bean->getFieldDefinitions();

			//find all definitions of type link.
			if (!empty($fieldDefs[$link_name])) {
				//initialize a variable of type Link
				$class = load_link_class($fieldDefs[$link_name]); // Link2

				//if rel_name is provided, search the fieldef array keys by name.
				if (isset($fieldDefs[$link_name]['type']) && $fieldDefs[$link_name]['type'] == 'link') {					
					if ($class == "Link2") {
						$link_obj = new $class($link_name, $bean);
					
						if ($link_obj->loadedSuccesfully()) {
							
							$relationship_obj = $link_obj->getRelationshipObject();

							// Getting the relationsthip_type. The param is weird as it doesn't have any effect (e.g M2MRelationship.php)
							$relationship_type = $relationship_obj->getType('');
							$relationship_table = $relationship_obj->getRelationshipTable();  

							if ($this->debug) {
								$this->writeln('Module is:' . $module);
								$this->writeln( 'Relationship: ' .  $fieldDefs[$link_name]['relationship']);
								$this->writeln('Relationship Obj is ' . get_class($relationship_obj));
								$this->writeln('Relationship type is: ' . $relationship_type);
								//$this->writeln('Relationship definition is: ' . $relationship_obj->def);
								$this->writeln('Relationship table: ' . $relationship_table);
							}
							if ($relationship_type == 'one') {
								//connect to the other old db
								$query = $this->getConnBk()->createQueryBuilder();
								$expr = $query->expr();
								
								if (isset($relationship_obj->def['join_table'])) { 
									$lhs_module = $relationship_obj->def['lhs_module'];
									$rhs_module = $relationship_obj->def['rhs_module'];

									if (isset($properties['module'])) {
										if ($properties['module'] == $lhs_module)
											$join_key_column = $relationship_obj->def['join_key_rhs'];
										else
											$join_key_column = $relationship_obj->def['join_key_lhs'];
									} else { 
										$join_key_column = $relationship_obj->def['join_key_rhs']; 
									}
								} else
									$join_key_column = $relationship_obj->def['rhs_key'];
								
								$query
									->select('id')
									->from($relationship_obj->getRelationshipTable())
									->where($expr->eq($join_key_column, ':join_key_column'))
									//->andWhere($expr->eq('deleted', 0))
									->setParameter('join_key_column', $this->record_id);

								$results = $query->execute()->fetchAll(\Doctrine\DBAL\FetchMode::COLUMN);
								
								// Important debug statement
								if ($this->debug) {
									$this->writeln($query->getSQL());
								}

								// Link them back with the parent record
								if (count($results) > 0) {
									$query
										->update($relationship_obj->getRelationshipTable())
										->set('deleted', 0)
										->where('id IN (:ids)')
										->setParameter('ids', $results, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
									
									// stock tables such as cases does not have a join table so we don't need to set the join column 
									if (!isset($relationship_obj->def['join_table'])) {
										$query->set($join_key_column, $expr->literal($this->record_id));
                                    }
                                    $this->writeln('');
									$this->writeln($this->makeSQL($query, $module, count($results)));
								}

							} else {
								$lhs_module = $relationship_obj->def['lhs_module'];
								$rhs_module = $relationship_obj->def['rhs_module'];

								// $properties['module'] is not reliable 
								if (isset($relationship_obj->def['join_table'])) {
									if (isset($properties['module'])) {
										if ($properties['module'] == $lhs_module)
											$join_key_column = $relationship_obj->def['join_key_rhs'];
										else
											$join_key_column = $relationship_obj->def['join_key_lhs'];
									} else {
										$join_key_column = $relationship_obj->def['join_key_rhs'];
									}
								}
								
								$query = $this->getConn()->createQueryBuilder();
								$expr = $query->expr();

								$query
									->select('id')
									->from($relationship_obj->getRelationshipTable())
									->where($expr->eq($join_key_column, ':join_key_column'))
									->andWhere($expr->eq('deleted', 1))
									->setParameters(['join_key_column' => $this->record_id, 'date_modified' => $bean->date_modified]);

								// Some table doesn't have date_modified
								$sm = $this->getConn()->getSchemaManager();
								$table = $sm->listTableDetails($relationship_obj->getRelationshipTable());
								if ($table->hasColumn('date_modified'))
									$query->andWhere($expr->eq('date_modified', ':date_modified'));

								// Important debug statement
								if ($this->debug) {
									$this->writeln($query->getSQL());
								}

								$results = $query->execute()->fetchAll(\Doctrine\DBAL\FetchMode::COLUMN);

								if (count($results) > 0) {
									$query
										->update($relationship_obj->getRelationshipTable())
										->set('deleted', 0);
										
									// Check if this is the Accounts or Contacts module, we need to mark the primary account 
									if ($properties['name'] == 'contacts' || $properties['name'] == 'accounts') {
										$query_backup = $this->getConnBk()->createQueryBuilder();
										
										$this->writeln('');
										$this->writeln('### Restoring ' . $properties['name'] . ' (' . count($results) . ' to be restored)');

										foreach ($results as $result) {
											$query_backup
												->select('primary_account')
												->from($relationship_obj->getRelationshipTable())
												->where($expr->eq('id', ':id'))
												->setParameter('id', $result);
												
											$primary_account = $query_backup->execute()->fetch(\Doctrine\DBAL\FetchMode::COLUMN);

											// Reset query part to avoid updating the same column primary_account multiple times
											$query->resetQueryPart('set');

											if ($primary_account == 1) {
												$query
													->set('deleted', 0)
													->set('primary_account', $primary_account)
													->where($expr->eq('id', ':id'))
													->setParameter('id', $result);
                                                    
                                                $this->writeln($this->makeSQL($query));
											}	
											
										}
										
									} else {
										$query
											->where('id IN (:ids)')
											->setParameter('ids', $results,\Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                                        
                                        $this->writeln('');
                                        $this->writeln($this->makeSQL($query, $module, count($results)));
									}
								}
							}
						}
					} 	
				}
			} 
		} 
    }
}