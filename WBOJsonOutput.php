<?php

# ***** BEGIN LICENSE BLOCK *****
# Version: MPL 1.1/GPL 2.0/LGPL 2.1
#
# The contents of this file are subject to the Mozilla Public License Version
# 1.1 (the "License"); you may not use this file except in compliance with
# the License. You may obtain a copy of the License at
# http://www.mozilla.org/MPL/
#
# Software distributed under the License is distributed on an "AS IS" basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
# for the specific language governing rights and limitations under the
# License.
#
# The Original Code is Weave Minimal Server
#
# The Initial Developer of the Original Code is
# Mozilla Labs.
# Portions created by the Initial Developer are Copyright (C) 2008
# the Initial Developer. All Rights Reserved.
#
# Contributor(s):
#	Toby Elliott (telliott@mozilla.com)
#   Luca Tettamanti
#
# Alternatively, the contents of this file may be used under the terms of
# either the GNU General Public License Version 2 or later (the "GPL"), or
# the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
# in which case the provisions of the GPL or the LGPL are applicable instead
# of those above. If you wish to allow use of your version of this file only
# under the terms of either the GPL or the LGPL, and not to allow others to
# use your version of this file under the terms of the MPL, indicate your
# decision by deleting the provisions above and replace them with the notice
# and other provisions required by the GPL or the LGPL. If you do not delete
# the provisions above, a recipient may use your version of this file under
# the terms of any one of the MPL, the GPL or the LGPL.
#
# ***** END LICENSE BLOCK *****


#The datasets we might be dealing with here are too large for sticking it all into an array, so
#we need to define a direct-output method for the storage class to use. If we start producing multiples
#(unlikely), we can put them in their own class.

class WBOJsonOutput
{
	private $_full = null;
	private $_comma_flag = 0;
	private $_output_format = 'json';

	function __construct ($full = false)
	{
		$this->_full = $full;
		if (array_key_exists('HTTP_ACCEPT', $_SERVER)
			&& !preg_match('/\*\/\*/', $_SERVER['HTTP_ACCEPT'])
			&& !preg_match('/application\/json/', $_SERVER['HTTP_ACCEPT']))
		{
			if (preg_match('/application\/whoisi/', $_SERVER['HTTP_ACCEPT']))
			{
				header("Content-type: application/whoisi");
				$this->_output_format = 'whoisi';
			}
			elseif (preg_match('/application\/newlines/', $_SERVER['HTTP_ACCEPT']))
			{
				header("Content-type: application/newlines");
				$this->_output_format = 'newlines';
			}

		}
	}

	function set_format($format)
	{
		$this->_output_format = $format;
	}


	function output($sth)
	{
		if (($rowcount = $sth->rowCount()) > 0)
		{
			header('X-Weave-Records: ' . $rowcount);
		}
		if ($this->_output_format == 'newlines')
		{
			return $this->output_newlines($sth);
		}
		elseif ($this->_output_format == 'whoisi')
		{
			return $this->output_whoisi($sth);
		}
		else
		{
			return $this->output_json($sth);
		}
	}

	function output_json($sth)
	{
		echo '[';

		while ($result = $sth->fetch(PDO::FETCH_ASSOC))
		{
			if ($this->_comma_flag) { echo ','; } else { $this->_comma_flag = 1; }
			if ($this->_full)
			{
				$wbo = new wbo();
				$wbo->populate($result);
				echo $wbo->json();
			}
			else
				echo json_encode($result{'id'});
		}

		echo ']';
		return 1;
	}

	function output_whoisi($sth)
	{
		while ($result = $sth->fetch(PDO::FETCH_ASSOC))
		{
			if ($this->_full)
			{
				$wbo = new wbo();
				$wbo->populate($result);
				$output = $wbo->json();
			}
			else
				$output = json_encode($result{'id'});
			echo pack('N', mb_strlen($output, '8bit')) . $output;
		}
		return 1;
	}

	function output_newlines($sth)
	{
		while ($result = $sth->fetch(PDO::FETCH_ASSOC))
		{
			if ($this->_full)
			{
				$wbo = new wbo();
				$wbo->populate($result);
				echo preg_replace('/\n/', '\u000a', $wbo->json());
			}
			else
				echo json_encode($result{'id'});
			echo "\n";
		}
		return 1;
	}
}
?>
