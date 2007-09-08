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

include_once("./functions/logging.php");
include_once("./functions/user.php");
include_once("./functions/utils.php");
include_once("./functions/http.php");
include_once("./functions/opendbmailer.class.php");

function is_valid_opendb_mailer()
{
	$mailer = get_opendb_config_var('email', 'mailer');
	return ($mailer == 'mail' || $mailer == 'smtp');
}

/**
	A simple email validation function.  Used by the main 'email' sending
	routine in this script, which allows calling programs to test their
	email addresses with the same test.
	
	Also used in functions/widgets.php 
*/
function is_valid_email_addr($email_addr)
{
	if ( strlen($email_addr)==0 || !ereg("^.+@.+\\..+$", $email_addr) || strpos($email_addr, ">")!==FALSE)
		return FALSE;
	else
		return TRUE;
}

/**
	Return email footer
*/
function get_email_footer()
{
	if(strlen(get_opendb_lang_var('email_footer'))>0)
	{
		$site_url = get_site_url();

		// This footer text will include any newlines that are required, and because the email
		// is text based, these newlines should be respected.  I am not sure what will happen
		// however on Mac / Windows systems, because we only understand '\n' as a newline
		// identifier in the language variables.
		$footer_text = get_opendb_lang_var('email_footer', array('site'=>get_opendb_config_var('site', 'title'),'version'=>get_opendb_version(),'site_url'=>$site_url));

		// Now lets explode any line breaks into separate lines so we
		// can work out how long the dashed lines need to be.
		$lines_r = explode("\n", $footer_text);
		if(is_array($lines_r) && count($lines_r)>1)
		{
			$length = 0;
			while(list(,$line) = @each($lines_r))
			{
				// We need to get the longest line.
				if(strlen($line)>$length)
					$length = strlen($line);
			}
		}
		else // Only one line.
		{
			$length = strlen($footer_text);
		}
		
		if($length>0)
		{
			for($i=0;$i<$length;$i++)
				$dashed_line .= "-";
			
			// Now return the complete footer text
			return	"\n\n".
					$dashed_line
					."\n"
					.$footer_text;
		}
	}

	//else - no footer.
	return "";
}

function send_email_to_site_admins($from_email_addr, $subject, $message, &$errors)
{
	$success = TRUE;
	
	if(!is_valid_email_addr($from_email_addr))
	{
		$errors[] = get_opendb_lang_var('invalid_from_address');
		$success = FALSE;
	}
	
	if(strlen($subject)==0)
	{
		$errors[] = get_opendb_lang_var('invalid_subject');
		$success = FALSE;
	}
	
	if($success)
	{
		$results = fetch_user_rs(array('A'));
		while($user_r = db_fetch_assoc($results))
		{
			if(!opendb_user_email($user_r['user_id'], $from_email_addr, $subject, $message, $errors))
			{
				$success = FALSE;
			}
		}
	}
	
	return $success;
}

/**
* Email to be sent from one OpenDb user to another
* 
* @from_userid can be null, and in this case, the from address will be the configured no-reply address for
* the psuedo administrator.
*/
function opendb_user_email($to_userid, $from_userid, $subject, $message, &$errors, $append_site_to_subject = TRUE)
{
	$to_userid = trim($to_userid);
	if(is_user_valid($to_userid))
	{
		$to_user_r = fetch_user_r($to_userid);
		$to_email_addr = trim($to_user_r['email_addr']);
		$to_name = trim($to_user_r['fullname']);

		$from_userid = trim($from_userid);
		if(is_user_valid($from_userid))
		{
			$from_user_r = fetch_user_r($from_userid);
			$from_email_addr = trim($from_user_r['email_addr']);
			$from_name = trim($from_user_r['fullname']);
		}
		else if($from_userid === NULL)
		{
			$from_email_addr = trim(get_opendb_config_var('email', 'noreply_address'));
			$from_name = trim(get_opendb_lang_var('noreply'));
		}
		else //if(is_valid_email_addr($from_userid))
		{
			$from_email_addr = $from_userid;
		}
		
		if(!is_valid_email_addr($to_email_addr))
		{
			$errors[] = get_opendb_lang_var('invalid_to_address');
			return FALSE;
		}
		
		if(!is_valid_email_addr($from_email_addr))
		{
			$errors[] = get_opendb_lang_var('invalid_from_address');
			return FALSE;
		}
	
		$subject = trim(stripslashes($subject));
		if (strlen($subject)==0)
		{
			$errors[] = get_opendb_lang_var('invalid_subject');
			return FALSE;
		}
	
		if($append_site_to_subject) {
			$subject .= " [".get_opendb_config_var('site', 'title')."]";
		}
		
		$message = trim(stripslashes($message));
		$message .= get_email_footer();
		
		if( sendEmail($to_email_addr, $to_name, $from_email_addr, $from_name, $subject, $message, $errors) ) {

			// save email record.
			insert_email(
				$to_userid, 
				$from_userid!=$from_email_addr?$from_userid:NULL, 
				$from_email_addr, // insert email function will set this to NULL if from user provided!
				$subject, 
				$message);
			
			// todo - save record of sent message
			return TRUE;	
		}
	}
	
	//else
	return FALSE;
}

/**
	@param to
	@param toname
	@param from
	@param fromname
	@param subject
	@param message

	@returns TRUE on success, or array of errors on failure.
*/
function sendEmail($to, $toname, $from, $fromname, $subject, $message, &$errors)
{
	$mailer = new OpenDbMailer(ifempty(get_opendb_config_var('email', 'mailer'), 'mail'));

	$mailer->From     = $from;
	$mailer->FromName = $fromname;

	$mailer->AddAddress($to, $toname);
	$mailer->Subject = $subject;
	$mailer->Body    = $message;

	if($mailer->Send())
	{
		// No errors returned indicates correct execution.
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, 'Email sent', array($to, $toname, $from, $fromname, $subject, "<snip>", $append_site_to_subject));
		return TRUE;
	}
	else
	{
		// No errors returned indicates correct execution.
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, $mailer->ErrorInfo, array($to, $toname, $from, $fromname, $subject, "<snip>", $append_site_to_subject));

		$errors[] = $mailer->ErrorInfo;
		return FALSE;
	}
}

/**
 * The table structure could be more sophisticated where a message is sent to multiple
 * addresses, but since the email function does not provide this, I see no reason to
 * do anything more complicated.
 *
 * @param unknown_type $item_id
 * @param unknown_type $author_id
 * @param unknown_type $comment
 * @param unknown_type $rating
 * @return unknown
 */
function insert_email($to_user_id, $from_user_id, $from_email_addr, $subject, $message)
{
	$to_user_id = trim($to_user_id);
	$from_user_id = trim($from_user_id);
	$from_email_addr = trim($from_email_addr);
	
	if(!is_user_valid($to_user_id)) {
		return FALSE;
	} else if( strlen($from_user_id)>0 && 
			( !is_user_valid($from_user_id) || $from_user_id == $to_user_id ) ) {
		return FALSE;
	} else if( strlen($from_user_id)==0 && 
			( strlen($from_email_addr)==0 || !is_valid_email_addr($from_email_addr)) ) {
		return FALSE;
	}
	
	if(strlen($from_user_id)>0) {
		$from_email_addr = NULL;
	} else {
		$from_email_addr = addslashes($from_email_addr);
	}
	
	$subject = addslashes(trim($subject));
	$message = addslashes(replace_newlines(trim($message)));

	$query = "INSERT INTO mailbox (to_user_id,from_user_id,from_email_addr,subject,message)".
			"VALUES ('$to_user_id',".(strlen($from_user_id)>0?"'$from_user_id'":"NULL").",".(strlen($from_email_addr)>0?"'$from_email_addr'":"NULL").", '$subject','$message')";

	$insert = db_query($query);
	if ($insert && db_affected_rows() > 0)
	{
		opendb_logger(OPENDB_LOG_INFO, __FILE__, __FUNCTION__, NULL, array($to_user_id, $from_user_id, $from_email_addr, $subject, NULL));
		return TRUE;
	}
	else
	{
		opendb_logger(OPENDB_LOG_ERROR, __FILE__, __FUNCTION__, db_error(), array($to_user_id, $from_user_id, $from_email_addr, $subject, NULL));
		return FALSE;
	}
}
?>