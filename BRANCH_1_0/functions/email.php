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
 * Its assumed that this function will only ever be used in the case where 
 */
function send_email_to_site_admins($from, $subject, $message, &$errors)
{
	if(!is_valid_email_addr($from))
	{
		$errors[] = get_opendb_lang_var('invalid_from_address');
		return FALSE;
	}
	
	$admin_name = get_opendb_lang_var('site_administrator', 'site', get_opendb_config_var('site', 'title'));
	
	$success = FALSE;
	
	$results = fetch_user_rs(array('A'));
	while($user_r = db_fetch_assoc($results))
	{
		if(opendb_email(fetch_user_email($user_r['user_id']), $admin_name, $from, NULL, $subject, $message, $errors))
		{
			$success = TRUE;
		}
	}
	
	return $success;
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
			return	$dashed_line
					."\n"
					.$footer_text
					."\n"
					.$dashed_line;
		}
	}

	//else - no footer.
	return "";
}

/**
* Email to be sent from one OpenDb user to another
*/
function opendb_user_email($to_userid, $from_userid, $subject, $message, &$errors)
{
	if(is_user_valid($to_userid) && is_user_valid($from_userid))
	{
		return opendb_email(
				fetch_user_email($to_userid),
				fetch_user_name($to_userid),
				fetch_user_email($from_userid),
				fetch_user_name($from_userid),
				$subject,
				$message,
				$errors);
	}
	else
	{
		return FALSE;
	}
}

/**
	@param to
	@param toname
	@param from
	@param fromname
	@param subject
	@param message
	@param append_site_to_subject Whether the [OpenDb] should be appended to
			the subject line.

	@returns TRUE on success, or array of errors on failure.
*/
function opendb_email($to, $toname, $from, $fromname, $subject, $message, &$errors, $append_site_to_subject = TRUE)
{
	// trim once only!
	$to = trim($to);
	$from = trim($from);

	$success = TRUE;
	
	if(!is_valid_email_addr($from))
	{
		$errors[] = get_opendb_lang_var('invalid_from_address');
		$success = FALSE;
	}
	
	if (strlen(trim($subject))==0)
	{
		$errors[] = get_opendb_lang_var('invalid_subject');
		$success = FALSE;
	}
	
	// Check 'to' address
	if(!is_valid_email_addr($to))
	{
		$errors[] = get_opendb_lang_var('invalid_to_address');
		$success = FALSE;
	}
	
	if($success)
	{
        $message =
				stripslashes($message).
					"\n\n\n".
					get_email_footer();

		$subject = stripslashes($subject).
					($append_site_to_subject?" [".get_opendb_config_var('site', 'title')."]":"");

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
	else
	{
		return FALSE;
	}
}
?>