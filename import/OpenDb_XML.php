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

include_once("./functions/XMLImportPlugin.class.php");

class OpenDb_XML extends XMLImportPlugin
{
	function OpenDb_XML() {
		parent::XMLImportPlugin();
	}
	
	function get_display_name()
	{
		return 'Open Media Collectors Database XML';
	}
	
	function get_plugin_type()
	{
		return 'xml';
	}
	
	function is_doctype_supported($docType)
	{
		return (strcasecmp($docType, 'Items') === 0);
	}
	
	function is_namespace_supported($nameSpace)
	{
		return (strcasecmp($nameSpace, 'http://opendb.iamvegan.net/xsd/Items-1.3.xsd') === 0);
	}
	
	function start_element($name, $attribs, $pcdata)
	{
		if(strcmp($name, 'Item')===0)
		{
			$this->startItem($attribs['ItemType']);
		}
		else if(strcmp($name, 'Title')===0)
		{
			$this->setTitle(unhtmlentities($pcdata));
		}
		else if(strcmp($name, 'Instance')===0)
		{
			$this->startItemInstance();
			$this->setInstanceStatusType($attribs['StatusType']);
			$this->setInstanceBorrowDuration($attribs['BorrowDuration']);
		}
		else if(strcmp($name, 'StatusComment')===0)
		{
			$this->setInstanceStatusComment(unhtmlentities($pcdata));
		}
		else if(strcmp($name, 'Attribute')===0)
		{
			$this->addAttribute($attribs['AttributeType'], NULL, unhtmlentities($pcdata));
		}
	}
	
	function end_element($name)
	{
		if(strcmp($name, 'Item')===0)
		{
			$this->endItem();
		}
		else if(strcmp($name, 'Instance')===0)
		{
			$this->endItemInstance();
		}
	}
}
?>