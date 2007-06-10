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

include_once('./functions/config.php');
include_once("./functions/item_type.php");
include_once("./functions/item_type_group.php");
include_once("./functions/item_listing_conf.php");
include_once("./admin/s_item_type/functions.php");

function get_column_prompts()
{
	return array(
				'',
				'Column Type',
				'Field Type',
				'Attribute Type',
				'Orderby Support',
				'Orderby Datatype',
				'Override Prompt',
				'Printable Support');				
}

/**
	Will not be called for a new record
*/
function is_field_disabled($name, $record_r)
{
	if($name == 's_field_type')
	{
		return ($record_r['column_type'] != 's_field_type');
	}
	else if($name == 's_attribute_type')
	{
		return ($record_r['column_type'] != 's_attribute_type');
	}
	else if($name == 'orderby_support_ind')
	{
		return ($record_r['column_type'] != 's_attribute_type' && $record_r['column_type'] != 's_field_type');
	}
	else if($name == 'orderby_datatype')
	{
		return ($record_r['orderby_support_ind'] == 'N' || 
			($record_r['column_type'] != 's_attribute_type' && $record_r['column_type'] != 's_field_type'));
	}
	else if($name == 'override_prompt')
	{
		return FALSE; // only ever disabled for new record
	}
	else if($name == 'printable_support_ind')
	{
		return ($record_r['column_type'] == 'action_links');
	}
}

function get_column_details($record_r, $row)
{
	if(is_not_empty_array($record_r))
		$new_record = FALSE;
	else
		$new_record = TRUE;

	$columns_r = NULL;
	
	$columns_r[] = array('column'=>'button',
				'field'=>'<a style="{cursor: pointer;}" onClick="moveRowUp(document.forms[\'s_item_listing_conf\'], document.forms[\'s_item_listing_conf\'][\'column_no['.$row.']\']); return false;"><img src="./admin/config/up.gif" border=0></a><br>'.
				'<a style="{cursor: pointer;}" onClick="moveRowDown(document.forms[\'s_item_listing_conf\'], document.forms[\'s_item_listing_conf\'][\'column_no['.$row.']\']); return false;"><input type=image src="./admin/config/down.gif" border=0></a>');
	
	$column_types_r = array(
		array('value'=>'', 'display'=>''),
		array('value'=>'s_field_type', 'display'=>'Field Type'),
		array('value'=>'s_attribute_type', 'display'=>'Attribute Type'),
		array('value'=>'action_links', 'display'=>'Action Links'),
		array('value'=>'borrow_status', 'display'=>'Borrow Status'));
		
	$columns_r[] = array(
		'column'=>'column_type',
		'field'=>format_field(
			'Column Type',
			NULL,
			custom_select(
				"column_type[$row]", 
				$column_types_r, 
				'%display%', 
				1,
				$record_r['column_type'],
				'value',
				NULL,
				'',
				'doOnChange(this.form, this)',
				FALSE),  // disabled
			FALSE));

	$field_type_r = array();
	if($new_record || $record_r['column_type'] != 's_field_type')
	{
		$field_type_r = array(
			array('value'=>'', 'display'=>''));
	}

	//ITEM_ID, TITLE, STATUSTYPE, STATUSCMNT, CATEGORY, RATING, ITEMTYPE, OWNER
	$field_type_r = array_merge(
		$field_type_r,
		array(
			array('value'=>'ITEM_ID', 'display'=>'Item ID'),
			array('value'=>'ITEMTYPE', 'display'=>'Item Type'),
			array('value'=>'TITLE', 'display'=>'Title'),
			array('value'=>'CATEGORY', 'display'=>'Category'),
			array('value'=>'STATUSTYPE', 'display'=>'Status Type'),
			array('value'=>'STATUSCMNT', 'display'=>'Status Comment'),
			array('value'=>'OWNER', 'display'=>'Owner'),
			array('value'=>'RATING', 'display'=>'Rating')));

	$columns_r[] = array(
		'column'=>'s_field_type',
		'field'=>format_field(
			'Field Type',
			NULL,
			custom_select(
				"s_field_type[$row]", 
				$field_type_r, 
				'%display%', 
				1,
				$record_r['s_field_type'],
				'value',
				NULL,
				'',
				'doOnChange(this.form, this)',
				$new_record || is_field_disabled('s_field_type', $record_r)),
			FALSE));

	$attribute_type_rs = NULL;
	if($new_record || $record_r['column_type'] != 's_attribute_type')
	{
		$attribute_type_rs = array(
			array('value'=>'', 'display'=>''));
	}
	
	$results = fetch_item_type_s_attribute_type_rs();
	if($results)
	{
		while($attribute_type_r = db_fetch_assoc($results))
		{
			$attribute_type_rs[] = array('value'=>$attribute_type_r['s_attribute_type'], 'display'=>$attribute_type_r['description']);
		}
		db_free_result($results);
	}

	// this is to avoid confusion if system data is defined for non-existent s_attribute_types
	if(!in_array($record_r['s_attribute_type'], $attribute_type_rs))
		$attribute_type_rs[] = array('value'=>$record_r['s_attribute_type'], 'display'=>$record_r['s_attribute_type']);
		
	$columns_r[] = array(
		'column'=>'s_attribute_type',
		'field'=>format_field(
			'Attribute Type', 
			NULL, 
			custom_select(
				"s_attribute_type[$row]", 
				$attribute_type_rs,
				'%value%', 
				1, 
				$record_r['s_attribute_type'],
				'value',
				NULL,
				'',
				'doOnChange(this.form, this)',
				$new_record || is_field_disabled('s_attribute_type', $record_r)),
			FALSE));
	
	$disabled = ($new_record || is_field_disabled('orderby_support_ind', $record_r));
	if($disabled)
		$record_r['orderby_support_ind'] = 'N';	
	
	$columns_r[] = array(
		'column'=>'orderby_support_ind',
		'field'=>get_input_field(
			'orderby_support_ind['.$row.']',
			NULL,
			'Order By Support',
			"simple_checkbox(".($record_r['orderby_support_ind']=='Y'?'CHECKED':'').")", 
			'N', 
			'Y', 
			FALSE,
			NULL,
			'doOnChange(this.form, this);',
			$new_record || is_field_disabled('orderby_support_ind', $record_r)));

	$datatypes_r = array();
	if(is_field_disabled('orderby_datatype', $record_r))
	{
		$datatypes_r = array(
			array('value'=>'', 'display'=>''));
	}

	$datatypes_r = array_merge(
		$datatypes_r,
		array(
			array('value'=>'alpha'),
			array('value'=>'numeric')));

	$columns_r[] = array(
		'column'=>'orderby_datatype',
		'field'=>format_field(
			'Orderby Datatypes',
			NULL,
			custom_select(
				"orderby_datatype[$row]", 
				$datatypes_r, 
				'%value%', 
				1,
				$record_r['orderby_datatype'],
				'value',
				NULL,
				'',
				'', // onChange
				$new_record || is_field_disabled('orderby_datatype', $record_r)), 
			FALSE));
					
	$columns_r[] = array(
		'column'=>'override_prompt',
		'field'=>get_input_field(
			"override_prompt[$row]", 
			NULL, 
			'Override Prompt', 
			'text(20,30)', 
			'N',
			$record_r['override_prompt'], 
			FALSE,
			'',
			'', // onChange
			$new_record || is_field_disabled('override_prompt', $record_r)));

	$disabled = ($new_record || is_field_disabled('printable_support_ind', $record_r));
	if($disabled)
		$record_r['printable_support_ind'] = 'N';
		
	$columns_r[] = array(
		'column'=>'printable_support_ind',
		'field'=>get_input_field(
			"printable_support_ind[$row]",
			NULL,
			'Printable Support',
			"simple_checkbox(".($record_r['printable_support_ind']=='Y'?'CHECKED':'').")", 
			'N', 
			'Y', 
			FALSE,
			'',
			'', // onchange
			$disabled));
	
	$buffer = "<tr>";
	
	$class = 'data';
	if(strlen($columns_r['error'])>0)
		$class = 'error';

	// column_no hidden must be null for us to determine if a row has been populated
	$buffer .= 	get_input_field(
		"column_no[$row]", 
		NULL, 
		'Column No', 
		'hidden', 
		'N',
		$record_r['column_no'], 
		FALSE);
	
	while(list(, $column_r) = each($columns_r))
	{
		$buffer .= '<td align="center" class="'.$class.'" id="'.$column_r['column'].'['.$row.']" nowrap>'.$column_r['field'].'</td>';
	}
	$buffer .= '</tr>';
	return $buffer;
}

session_start();
if(is_opendb_valid_session())
{
    if(is_user_admin(get_opendb_session_var('user_id'), get_opendb_session_var('user_type')))
	{
	    if($HTTP_VARS['op'] == 'update')
		{
			if(($HTTP_VARS['s_item_type_group'] == '*' || is_exists_item_type_group($HTTP_VARS['s_item_type_group'])) &&
					($HTTP_VARS['s_item_type'] == '*' || is_exists_item_type($HTTP_VARS['s_item_type'])))
			{
				$column_conf_rs = NULL;
				
				$errors_found = FALSE;
				
				for($row=0; $row<count($HTTP_VARS['column_no']); $row++)
				{
					if(validate_column_type($HTTP_VARS['column_type'][$row]))
					{
						$column_conf_r = array(
								'column_no'=>$row,
								'column_type'=>$HTTP_VARS['column_type'][$row],
								's_field_type'=>$HTTP_VARS['s_field_type'][$row],
								's_attribute_type'=>$HTTP_VARS['s_attribute_type'][$row],
								'override_prompt'=>$HTTP_VARS['override_prompt'][$row],
								'orderby_support_ind'=>$HTTP_VARS['orderby_support_ind'][$row],
								'orderby_datatype'=>$HTTP_VARS['orderby_datatype'][$row],
								'printable_support_ind'=>$HTTP_VARS['printable_support_ind'][$row]);

						if(validate_item_column_conf_r($column_conf_r, $error))
						{
							$column_conf_rs[] = $column_conf_r;
						}
						else
						{
							$column_conf_r['error'] = $error;
							$column_conf_rs[] = $column_conf_r;
							$errors_found = TRUE;
						}
					}
				}

				if(!$errors_found)
				{
					$HTTP_VARS['silc_id'] = fetch_s_item_listing_conf_id($HTTP_VARS['s_item_type_group'], $HTTP_VARS['s_item_type']);
					
					if(is_not_empty_array($column_conf_rs))
					{
						// if no parent item_listing_conf record, we must create one now
						if(!is_numeric($HTTP_VARS['silc_id']))
						{
							$HTTP_VARS['silc_id'] = insert_s_item_listing_conf($HTTP_VARS['s_item_type_group'], $HTTP_VARS['s_item_type']);
						}
					
						// delete all column conf and insert new set
						insert_new_column_conf_set($HTTP_VARS['silc_id'], $column_conf_rs);
					}
					else
					{
						if(is_numeric($HTTP_VARS['silc_id']))
						{
							delete_s_item_listing_column_conf($HTTP_VARS['silc_id']);
							
							//delete parent too
							delete_s_item_listing_conf($HTTP_VARS['silc_id']);
						}
					}
				}
				
				$HTTP_VARS['op'] = 'edit';
			}//if(is_exists_s_item_listing_conf($HTTP_VARS['silc_id']))
			else
			{
				// error no item found!
				$HTTP_VARS['op'] = '';
	    	}
	    }
	    
	    // in edit mode, either its a new entry, in which case s_item_type_group / s_item_type is chosen, or its
	    // an existing item, in which case the silc_id will be provided.
		if($HTTP_VARS['op'] == 'edit')
		{
			if(($HTTP_VARS['s_item_type_group'] == '*' || is_exists_item_type_group($HTTP_VARS['s_item_type_group'])) &&
					($HTTP_VARS['s_item_type'] == '*' || is_exists_item_type($HTTP_VARS['s_item_type'])))
			{
				echo("<script language=\"JavaScript\" type=\"text/javascript\" src=\"$ADMIN_DIR/rowutils.js\"></script>");
				echo('<style>
						.dataHighlight {background-color: #BDC7F7;font-size: x-small;font-weight: normal;font-family: Verdana, Arial, Helvetica, sans-serif; padding-left: 4px; padding-right: 4px;}
				</style>');
				
				echo("<div class=\"footer\">[<a href=\"$PHP_SELF?type=$ADMIN_TYPE\">Back to Main</a>]</div>");

				if($HTTP_VARS['s_item_type_group'] != '*')
					echo("\n<h3>Edit Item Type Group ".$HTTP_VARS['s_item_type_group']." Item Listing Configuration</h3>");
				else if($HTTP_VARS['s_item_type'] != '*')
                    echo("\n<h3>Edit Item Type ".$HTTP_VARS['s_item_type']." Item Listing Configuration</h3>");
				else
                    echo("\n<h3>Edit Default Item Listing Configuration</h3>");

				if(is_not_empty_array($errors))
					echo format_error_block($errors);

				$prompts_r = get_column_prompts();
					
            	echo("\n<table>");
        	    echo("\n<form name=\"s_item_listing_conf\" action=\"$PHP_SELF\" method=\"POST\">");
    	        echo("\n<input type=\"hidden\" name=\"op\" value=\"\">");
				echo("\n<input type=\"hidden\" name=\"type\" value=\"".$ADMIN_TYPE."\">");
    	        echo("\n<input type=\"hidden\" name=\"s_item_type_group\" value=\"".$HTTP_VARS['s_item_type_group']."\">");
    	        echo("\n<input type=\"hidden\" name=\"s_item_type\" value=\"".$HTTP_VARS['s_item_type']."\">");
				
    	        echo '<tr class="navbar">';
				// now we want to build the input form
				for($i=0; $i<count($prompts_r); $i++)
				{
					echo '<th>'.$prompts_r[$i].'</th>';
				}
				echo '</tr>';

				$row = 0;
				
				$HTTP_VARS['silc_id'] = fetch_s_item_listing_conf_id($HTTP_VARS['s_item_type_group'], $HTTP_VARS['s_item_type']);
				if(is_numeric($HTTP_VARS['silc_id']))
				{
					$results = fetch_s_item_listing_column_conf_rs($HTTP_VARS['silc_id']);
	        		if($results)
					{
						while($item_listing_column_conf_r = db_fetch_assoc($results))
						{
							echo get_column_details($item_listing_column_conf_r, $row);
							$row++;
						}
						db_free_result($results);
					}
				}
				
				if(is_numeric($HTTP_VARS['blank_rows']))
					$blank_rows = (int)$HTTP_VARS['blank_rows'];
				else if(is_numeric($HTTP_VARS['silc_id']))
					$blank_rows = 5;
				else
					$blank_rows = 10;
	
				for($i=$row; $i<$row+$blank_rows; $i++)
				{
					echo get_column_details(array(), $i);
				}
			
				echo("<tr>");
				echo("<td colspan=2 align=left>".
					get_input_field("blank_rows", NULL, NULL, "value_select(\"1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20\",1)", "N", $blank_rows, FALSE, NULL, "this.form.submit();")
					."</td>");

				echo("<td colspan=10 align=center>");
				echo("<input type=button value=\"Refresh\" onclick=\"this.form['op'].value='edit'; this.form.submit();\">&nbsp;<input type=button value=\"Update\" onclick=\"this.form['op'].value='update'; this.form.submit();\"></td>");
				echo("</tr>");
				
				echo("</form>");
				echo '</table>';
			}
			else
			{
				// error no item found!
				$HTTP_VARS['op'] = '';
			}
	    }

		if($HTTP_VARS['op'] == '')
	    {
            if(is_not_empty_array($errors))
				echo format_error_block($errors);

			echo("<form name=\"navigate\" action=\"$PHP_SELF\" method=\"GET\">".
				"<input type=\"hidden\" name=\"type\" value=\"".$ADMIN_TYPE."\">".
				"<input type=\"hidden\" name=\"op\" value=\"\">".
				"<input type=\"hidden\" name=\"s_item_type_group\" value=\"\">".
				"<input type=\"hidden\" name=\"s_item_type\" value=\"\">".
				"</form>");

			echo("<table cellspacing=2 border=0 width=200>");
			echo("\n<form name=\"s_item_listing_conf\" action=\"$PHP_SELF\" method=\"POST\">");
			echo("\n<input type=\"hidden\" name=\"type\" value=\"".$ADMIN_TYPE."\">");

			echo("<tr class=\"navbar\">");
			echo("<th colspan=2>Item Type Groups</th>");
			echo("</tr>");
					
            $results = fetch_item_type_group_rs();
			if($results)
			{
				while($item_type_group_r = db_fetch_assoc($results))
				{
				    echo("\n<tr><td class=\"data\" align=center>".$item_type_group_r['s_item_type_group']."</td>");
				    echo("\n<td class=\"data\" align=center>");
					echo("\n<a href=\"${PHP_SELF}?type=${ADMIN_TYPE}&op=edit&s_item_type_group=".$item_type_group_r['s_item_type_group']."&s_item_type=*\">Edit</a>");
					echo("\n</td></tr>");
				}
				db_free_result($results);
			}
	
			echo("<tr class=\"navbar\">");
			echo("<th colspan=2>Item Types</th>");
			echo("</tr>");

			$results = fetch_s_item_type_rs('s_item_type');
			if($results)
			{
				while($item_type_r = db_fetch_assoc($results))
				{
					echo("\n<tr><td class=\"data\" align=center>".$item_type_r['s_item_type']."</td>");
				    echo("\n<td class=\"data\" align=center>");
					echo("\n<a href=\"${PHP_SELF}?type=${ADMIN_TYPE}&op=edit&s_item_type_group=*&s_item_type=".$item_type_r['s_item_type']."\">Edit</a>");
					echo("\n</td></tr>");
				}
				db_free_result($results);
			}
			
			echo("\n<tr><td class=\"data\" align=center><strong>Default</strong></td>");
			echo("\n<td class=\"data\" align=center>");
			echo("\n<a href=\"${PHP_SELF}?type=${ADMIN_TYPE}&op=edit&s_item_type_group=*&s_item_type=*\">Edit</a>");
			echo("\n</td></tr>");
			
			echo("</form>");
			echo("</table>");
	    }
	}
}//if(is_opendb_valid_session())
?>