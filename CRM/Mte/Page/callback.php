<?php

/**
 * Mandrill Transactional Email extension integrates CiviCRM's non-bulk email 
 * with the Mandrill service
 * 
 * Copyright (C) 2012 JMA Consulting
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * Support: https://github.com/JMAConsulting/biz.jmaconsulting.mte/issues
 * 
 * Contact: info@jmaconsulting.biz
 *          JMA Consulting
 *          215 Spadina Ave, Ste 400
 *          Toronto, ON  
 *          Canada   M5T 2C7
 */

class CRM_Mte_Page_callback extends CRM_Core_Page {

  
  function run() {
    /*
    // We are not using Secret code for mandrill extension
    $secretCode = CRM_Utils_Type::escape($_GET['mandrillSecret'], 'String');
    $mandrillSecret = CRM_Mte_Mandrill::getSettings('secret_code');
    if ($secretCode != $mandrillSecret) {
      //return FALSE;
    }
    */
    $currentVer = CRM_Core_BAO_Domain::version();
    if (version_compare($currentVer, '4.4.alpha1') < 0) {
      $mailing_job_file = 'CRM_Mailing_DAO_Job';
    } else {
      $mailing_job_file = 'CRM_Mailing_DAO_MailingJob';
    }
    if (CRM_Utils_Array::value('mandrill_events', $_POST)) {
      $bounceType = array();
      $reponse = json_decode($_POST['mandrill_events'], TRUE);
      if (is_array($reponse)) {
        $subaccount = CRM_Mte_Mandrill::getSettings('subaccount');
        $events = array('open','click','hard_bounce','soft_bounce','spam','reject', 'send');
        foreach ($reponse as $value) {
          //changes done to check if email exists in response array
          if (in_array($value['event'], $events) && CRM_Utils_Array::value('email', $value['msg']) && 
            CRM_Utils_Array::value('subaccount', $value['msg']) == $subaccount ) {
            $civimail_bounce_id = CRM_Utils_Array::value('X-CiviMail-Bounce', $value['msg']['metadata'], null);
            $mail_id = '';
            $is_trx_email = false;
            if ($civimail_bounce_id) {
              $dao             = new CRM_Core_DAO_MailSettings;
              $dao->domain_id  = CRM_Core_Config::domainID();
              $dao->is_default = TRUE;
              if ( $dao->find(true) ) {
                $rpRegex = '/^' . preg_quote($dao->localpart) . '(b|c|e|o|r|u)\.(\d+)\.(\d+)\.([0-9a-f]{16})/';
              } else {
                $rpRegex = '/^(b|c|e|o|r|u)\.(\d+)\.(\d+)\.([0-9a-f]{16})/';
              }
              $matches = array();
              preg_match($rpRegex, $civimail_bounce_id, $matches);
              
              list($match, $action, $job, $queue, $hash) = $matches;
              $event_queue_id = $queue;
              if ($job) {
                $mail_id = CRM_Core_DAO::getFieldValue($mailing_job_file, $job, 'mailing_id', 'id');
              }
              
            } 

            else {
              $mail = new CRM_Mailing_DAO_Mailing();
              $mail->domain_id       = CRM_Core_Config::domainID();
              $mail->subject         = "***All Transactional Emails***";
              $mail->url_tracking    = TRUE;
              $mail->forward_replies = FALSE;
              $mail->auto_responder  = FALSE;
              $mail->open_tracking   = TRUE;
              
              $contacts = array();
              if ($mail->find(TRUE)) {
                $is_trx_email = true;
                $mail_id = $mail->id;
              }
            }

            
            if ($mail_id) {
              $emails = self::retrieveEmailContactId($value['msg']['email']);
              

              if ($is_trx_email) {
                if (!CRM_Utils_Array::value('contact_id', $emails['email'])) {
                  continue;
                }
                $params = array(
                  'job_id' => CRM_Core_DAO::getFieldValue($mailing_job_file, $mail_id, 'id', 'mailing_id'),
                  'contact_id' => $emails['email']['contact_id'],
                  'email_id' => $emails['email']['id'],
                  'activity_id' => CRM_Utils_Array::value('metadata', $value['msg']) ? CRM_Utils_Array::value('CiviCRM_Mandrill_id', $value['msg']['metadata']) : null
                );

                if (!empty($params['activity_id'])) {
                  $isActivityPresent = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $params['activity_id'], 'id', 'id');
                  if (empty($isActivityPresent)) {
                    unset($params['activity_id']);
                  }
                }

                $eventQueue = CRM_Mailing_Event_BAO_Queue::create($params);
                $event_queue_id = $eventQueue->id;
              }
              $bType = ucfirst(preg_replace('/_\w+/', '', $value['event']));
              $assignedContacts = array();
              switch ($value['event']) {
              case 'open':
                $oe                 = new CRM_Mailing_Event_BAO_Opened();
                $oe->event_queue_id = $event_queue_id;
                $oe->time_stamp     = date('YmdHis', $value['ts']);
                $oe->save();
                break;
                
              case 'click':
                $tracker = new CRM_Mailing_BAO_TrackableURL();
                $tracker->url = $value['url'];
                $tracker->mailing_id = $mail_id;
                if (!$tracker->find(TRUE)) {
                  $tracker->save();
                }
                $open = new CRM_Mailing_Event_BAO_TrackableURLOpen();
                $open->event_queue_id = $event_queue_id;
                $open->trackable_url_id = $tracker->id;
                $open->time_stamp = date('YmdHis', $value['ts']);
                $open->save();
                break;
                
              case 'hard_bounce':
              case 'soft_bounce':
              case 'spam':
              case 'reject':
                if (empty($bounceType)) {
                  CRM_Core_PseudoConstant::populate($bounceType, 'CRM_Mailing_DAO_BounceType', TRUE, 'id', NULL, NULL, NULL, 'name');
                }
                // Bounce Params
                $bounceParams = array();
                $bounceParams['time_stamp']     =  date('YmdHis', $value['ts']);
                $bounceParams['event_queue_id'] = $event_queue_id;
                $bounceParams['job_id']         = $job;
                $bounceParams['hash']           = $hash;
                $bounceParams['bounce_type_id'] = $bounceType["Mandrill $bType"];
                $bounceParams['bounce_reason']  = CRM_Core_DAO::getFieldValue('CRM_Mailing_DAO_BounceType', $bounceType["Mandrill $bType"], 'description');
                CRM_Mailing_Event_BAO_Bounce::create($bounceParams);
                if (substr($value['event'], -7) == '_bounce') {
                  $mailingBackend = CRM_Core_BAO_Setting::getItem(CRM_Core_BAO_Setting::MAILING_PREFERENCES_NAME,
                    'mailing_backend'
                  );
                  
                  // Send email notification only for Transactional Email
                  if (CRM_Utils_Array::value('group_id', $mailingBackend) && $is_trx_email) {
                    list($domainEmailName, $domainEmailAddress) = CRM_Core_BAO_Domain::getNameAndEmail();
                    $msgBody = '';
                    if (CRM_Utils_Array::value('metadata', $value['msg']) && CRM_Utils_Array::value('CiviCRM_Mandrill_id', $value['msg']['metadata'])) { 
                      $msgBody = CRM_Core_DAO::getFieldValue('CRM_Activity_DAO_Activity', $value['msg']['metadata']['CiviCRM_Mandrill_id'], 'details');
                    }
                    $mailBody = "The following email failed to be delivered due to a {$bType} Bounce :</br>
To: {$value['msg']['email']} </br>
From: {$value['msg']['sender']} </br>
Subject: {$value['msg']['subject']}</br>
Message Body: {$msgBody}" ;
                    $mailParams = array(
                      'groupName' => 'Mandrill bounce notification',
                      'from' => '"' . $domainEmailName . '" <' . $domainEmailAddress . '>',
                      'subject' => 'Mandrill Bounce Notification',
                      'text' => $mailBody,
                      'html' => $mailBody,
                    );
                    
                    $query = "SELECT ce.email, cc.sort_name, cgc.contact_id FROM civicrm_contact cc
INNER JOIN civicrm_group_contact cgc ON cgc.contact_id = cc.id
INNER JOIN civicrm_email ce ON ce.contact_id = cc.id
WHERE cc.is_deleted = 0 AND cc.is_deceased = 0 AND cgc.group_id = {$mailingBackend['group_id']} AND ce.is_primary = 1 AND ce.email <> %1";
                    $queryParam = array(1 => array($value['msg']['email'], 'String'));
                    $dao = CRM_Core_DAO::executeQuery($query, $queryParam);
                    while ($dao->fetch()) {
                      $mailParams['toName'] = $dao->sort_name;
                      $mailParams['toEmail'] = $dao->email;
                      CRM_Utils_Mail::send($mailParams);
                      $assignedContacts[] = $dao->contact_id;
                    }
                  }
                  $bType = 'Bounce';
                }
                break;
              }

              // create activity for click and open event
              if ( in_array($value['event'], array('open', 'click', 'send') ) || $bType == 'Bounce') {
                $activityTypes = CRM_Core_PseudoConstant::activityType(TRUE, FALSE, FALSE, 'name');
                $sourceContactId = self::retrieveEmailContactId($value['msg']['sender'], TRUE);

                // Update activity status only for civimail activity
                if ( ! $is_trx_email && ( $bType == 'Bounce' || $value['event'] == 'send') ) {
                  $activity_id = CRM_Utils_Array::value('CiviCRM_Mandrill_id', $value['msg']['metadata']);
                  $get_activity = civicrm_api( 'activity','get',array('id' => $activity_id, 'version' => 3 ) );
                  if (!empty($get_activity['values'])) {
                    $q = &CRM_Mailing_Event_BAO_Queue::verify($job,$event_queue_id, $hash );
                    $activityParams = $get_activity['values'][$activity_id];
                    $activityParams['status_id'] = 5; //Unreachable, change status to Unreachable
                    if ($value['event'] == 'send' ) {
                      $activityParams['status_id'] = 2; //Completed, change status to Completed
                    }
                    if($q->contact_id) {
                      $activityParams['target_contact_id'] = $q->contact_id;
                    }
                    $activityParams['version']   = 3;
                    civicrm_api('activity','create',$activityParams);
                  } else {
                    // For CiviMail update activity only for bounce type event
                    continue;
                  }
                } else if ( $is_trx_email && ($value['event'] == 'open' || $value['event'] == 'click' || $bType == 'Bounce') ) {
                  if (!CRM_Utils_Array::value('contact_id', $sourceContactId['email'])) {
                    continue;
                  }

                  // create  new activity for transactional emails
                  $activityParams = array( 
                    'source_contact_id' => $sourceContactId['email']['contact_id'],
                    'activity_type_id'  => array_search("Mandrill Email $bType", $activityTypes),
                    'subject'           => CRM_Utils_Array::value('subject', $value['msg']) ? $value['msg']['subject'] : "Mandrill Email $bType",
                    'activity_date_time'=> date('YmdHis'),
                    'status_id'         => 2,
                    'priority_id'       => 1,
                    'version'           => 3,
                    'target_contact_id' => $emails['contactIds'],
                  );
                  if (!empty($assignedContacts)) {
                    $activityParams['assignee_contact_id'] = $assignedContacts;
                    $activityParams['details'] = $mailBody;
                  }
                  civicrm_api('activity','create',$activityParams);
                }
              }

            }
          }
        }
      }
    }
    CRM_Utils_System::civiExit();
  }

  /* Function to retrieve email details of sender and to
   * 
   * $email string email id
   * $checkUnique to check unique email id i.e no more then 1 contact for a email ID.
   *
   */
  function retrieveEmailContactId($email, $checkUnique = FALSE) {
    if(!$email) {
      return FALSE;
    }
    $emails['email'] = null;
    $params = array( 
      'email' => $email,
      'version' => 3,
    );
    $result = civicrm_api( 'email','get',$params );
    // changes done for bad data, sometimes there are multiple emails but without contact id   
    foreach ($result['values'] as $emailId => $emailValue) {
      if (CRM_Utils_Array::value('contact_id', $emailValue)) {
        if (CRM_Utils_Array::value('email', $emails) && $checkUnique) {
          //return FALSE;
          return $emails;
        }
        if (!CRM_Utils_Array::value('email', $emails)) {
          $emails['email'] = $emailValue;
        }
        if (!$checkUnique) {
          $emails['contactIds'][] = $emailValue['contact_id'];
        }
      }
    }     
    return $emails;
  }
}

