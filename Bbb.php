<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
// ------------------------------------------------------------------------

/**
 * BBB Library Class (BigBlueButton Wrapper Class)
 *
 * This CI library is based on BigBlueButton's API (http://bigbluebutton.org)
 *
 * This file provides an easier and simpler way to use BigBlueButton on
 * CodeIgniter PHP Framework. This file is ONLY intended to be a CI wrapper
 * of BigBlueButton's official API.
 *
 * BigBlueButton is an open source web conferencing system built
 * on over fourteen open source components to create an integrated solution
 * that runs on Mac, UNIX, or PC computers.
 * BigBlueButton is trademark of BigBlueButton Inc.
 *
 * The class requires the use of the BBB config file.
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Web Conferencing
 * @author      Vasilis Lolos <vlolos@me.com>
 * @copyright   Copyright (c) 2011, https://github.com/lolos/BigBlueButton-CodeIgniter
 *
 */

// ------------------------------------------------------------------------

class Bbb {

    function Bbb()
	{
		$this->CI =& get_instance();
		$this->CI->load->helper('url');
		$this->CI->load->config('bbb_config');

        $this->api_url          = 'http://'.$this->CI->config->item('bbb_server_domain').'/bigbluebutton/api/';
        $this->security_salt    = $this->CI->config->item('bbb_security_salt');
        $this->max_participants = $this->CI->config->item('bbb_max_participants');
	}

	protected function get_query_string($params_input)
	{
		$params = array();

		foreach ( $params_input as $name => $value )
			array_push($params, urlencode($name).'='.urlencode($value));

		return implode('&', $params);
	}

	protected function get_checksum($callname, $params)
	{
		return sha1($callname.$this->get_query_string($params).$this->security_salt);
	}

	protected function get_call_url($callname, $params)
	{
		$params->checksum = $this->get_checksum($callname, $params);

		return $this->api_url.$callname.'?'.$this->get_query_string($params);
	}

	protected function get_xml_response($callname, $params)
	{
		$requestUrl = $this->get_call_url($callname, $params);

		if ( extension_loaded('curl') )
		{
			$ch = curl_init() or die(curl_error());

			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_URL, $requestUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

			$response = curl_exec($ch);

			curl_close( $ch );

			if ( $response )
				return new SimpleXMLElement($response);
		}

		return simplexml_load_file($requestUrl);
	}

	public function create_meeting($meetingID, $name, $duration=60, $logoutURL=NULL, $moderatorPW=NULL, $attendeePW=NULL, $welcome=NULL)
	{
		global $CFG;

		$params->name				= $name;
		$params->meetingID			= $meetingID;
		$params->maxParticipants	= $this->max_participants;
		$params->duration			= $duration;

		if (! empty($logoutURL) )
			$params->logoutURL = $logoutURL;

		if (! empty($moderatorPW) )
			$params->moderatorPW = $moderatorPW;

		if (! empty($attendeePW) )
			$params->attendeePW = $attendeePW;

		if (! empty($meeting) )
			$params->welcome = $welcome;

		return $this->get_xml_response('create', $params);
	}

	public function join_meeting($meetingID, $userID, $fullName, $password)
	{
		$params->fullName	= $fullName;
		$params->meetingID	= $meetingID;
		$params->password	= $password;
		$params->userID		= $userID;

		$requestUrl = $this->get_call_url('join', $params);

		redirect($requestUrl);
	}

	public function is_meeting_running($meetingID)
	{
		$params['meetingID']	= $meetingID;
		$params['checksum']	= $this->get_checksum('isMeetingRunning', $params);

		$xml = $this->get_xml_response('isMeetingRunning', $params);

		if ( $xml->running == 'true' )
			return TRUE;
	}

	public function end_meeting($meetingID, $moderatorPW)
	{
		$params->meetingID	= $meetingID;
		$params->password	= $moderatorPW;

		return $this->get_xml_response('end', $params);
	}

	public function get_meeting_info($meetingID, $moderatorPW)
	{
		$params->meetingID	= $meetingID;
		$params->password	= $moderatorPW;

		return $this->get_xml_response('getMeetingInfo', $params);
	}

	public function get_meetings()
	{
		$params->random = rand()*1000;

		$xml = $this->get_xml_response('getMeetings', $params);

		if( $xml && $xml->returncode == 'SUCCESS' )
		{
			if( count($xml->meetings) && count($xml->meetings->meeting) )
			{
				$meetings = array();

				foreach ( $xml->meetings->meeting as $m )
				{
					$meeting = $this->get_meeting_info($m->meetingID, $m->moderatorPW);

					array_push($meetings, $meeting);
				}

				return $meetings;
			}
		}
	}
}

/* End of file Bbb.php */