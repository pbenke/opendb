<?php
/* 	
	Open Media Collectors Database
	Copyright (C) 2001,2006 by Jason Pell

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

include_once("./functions/OpenDbUpgrader.class.php");

class Upgrader_100_110 extends OpenDbUpgrader
{
	function Upgrader_100_110()
	{
		parent::OpenDbUpgrader(
						'1.0',
						'1.1.0',
						array(
							array('description'=>'New Related Items Table and Misc Updates'),
							array('description'=>'New Related Status Type'),
							array('description'=>'Transfer Linked Items'),
							array('description'=>'Transfer Email Addresses'),
							array('description'=>'Cleanup Email address system data'),
							array('description'=>'Finalise upgrade')
						)
					);
	}
	
	function executeStep2($stepPart)
	{
		return exec_install_sql_file("./admin/s_status_type/sql/R-Related.sql", $errors);
	}
	
	/**
	 * Create a item_instance for every item that has a parent_id set.  Then create
	 * a item_instance_relationship to link it to all the parent item instances.  Finally
	 * drop the parent_id column.
	 */
	function executeStep3($stepPart)
	{
		$results = db_query(
					"SELECT ii.item_id, ii.instance_no, ii.owner_id, i.id AS related_item_id
					FROM	item i,
							item_instance ii 
					WHERE	i.parent_id = ii.item_id AND 
							i.parent_id IS NOT NULL");
		if($results)
		{
			while($item_instance_r = db_fetch_assoc($results))
			{
				if(!is_exists_item_instance($item_instance_r['related_item_id'], 1))
				{
					insert_item_instance(
							$item_instance_r['related_item_id'], 
							1, //$instance_no, 
							'R', //$s_status_type, 
							NULL, //$status_comment, 
							NULL, //$borrow_duration, 
							$item_instance_r['owner_id']);
				}
					
				insert_item_instance_relationship(
							$item_instance_r['item_id'], 
							$item_instance_r['instance_no'], 
							$item_instance_r['related_item_id'], 
							1);
			}
			db_free_result($results);
		}
		
		return TRUE;
	}
	
	function executeStep4($stepPart)
	{
		$results = db_query(
					"SELECT ua.user_id, uaa.attribute_val
					FROM user_address ua, user_address_attribute uaa
					WHERE ua.sequence_number = uaa.ua_sequence_number AND 
					ua.s_address_type = 'EMAIL' AND
					ua.start_dt <= NOW() AND (ua.end_dt IS NULL OR ua.end_dt < NOW())");
		if($results)
		{
			$result = TRUE;

			while($addr_attr_r = db_fetch_assoc($results))
			{
				if(is_valid_email_addr($addr_attr_r['attribute_val'])) {
					if(db_query("UPDATE user SET email_addr = '".$addr_attr_r['attribute_val']."'
								WHERE user_id = '".$addr_attr_r['user_id']."'") === FALSE )
					{
						$this->addError(
								'User '.$addr_attr_r['user_id'].' email address ('.$addr_attr_r['attribute_val'].') not transferred',
								db_error());
								
						$result = FALSE;
					}
				}
			}
			
			return $result;
		}
		
		return TRUE;
	}
	
	function executeStep6($stepPart)
	{
		db_query("ALTER TABLE item DROP parent_id");
		return TRUE;
	}
	
	/**
	 * @param unknown_type $stepPart
	 */
	function executeStep7($stepPart)
	{
		// need to copy all uploaded records from file_cache into upload directory creating using a 
		// unique filename
		
		$results = db_query("SELECT fc.cache_file, ia.item_id, ia.instance_no, ia.s_attribute_type, ia.order_no, ia.attribute_val
						FROM file_cache fc,
							item_attribute ia
						WHERE fc.upload_file_ind = 'Y' AND fc.cache_type = 'ITEM' AND
						fc.url = CONCAT( 'file://opendb/upload/', ia.item_id, '/', ia.instance_no, '/', ia.s_attribute_type, '/', ia.order_no, '/', ia.attribute_no, '/', ia.attribute_val )
						");
		if($results)
		{
			while($fc_attrib_r = db_fetch_assoc($results))
			{
				if(file_exists('./itemcache/'.$fc_attrib_r['cache_file']))
				{
					// todo - ensure attribute_val is unique filename, this can be done by locking the
					// item_attribute table and ensuring that where s_attribute_type file_attribute_ind = 'Y'
					// and attribute_val is not absolute that filename is unique, excluding current
					// attribute_val. 
					save_upload_file('./itemcache/'.$fc_attrib_r['cache_file'], $fc_attrib_r['attribute_val']);		
				}
			}
			db_free_result($results);
		}
	}
}
?>