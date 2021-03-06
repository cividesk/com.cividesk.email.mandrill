{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
<table class='removeAfter' style = "display:none;"><tbody>
  <tr class="crm-smtp-form-block-mandril_post_url">
    <td class="label">{$form.mandril_post_url.label}</td>
    <td>{$form.mandril_post_url.html}</td>
  </tr>
  <tr class="crm-smtp-form-block-notify_group">
    <td class="label">{$form.group_id.label}</td>
    <td>{$form.group_id.html}
      </br><span class="description">{ts}Group to notify for hard and soft bounce.{/ts}</span>
    </td>
  </tr>
  <tr class="crm-smtp-form-block-subaccount">
    <td class="label">{$form.subaccount.label}</td>
    <td>{$form.subaccount.html}</td>
  </tr>    
</tbody></table>
<script type="text/javascript">
{literal}
cj(document).ready(function(){
  cj('.crm-smtp-form-block-mandril_post_url').insertAfter('.crm-smtp-form-block-smtpPassword');
  cj('.crm-smtp-form-block-notify_group').insertAfter('.crm-smtp-form-block-mandril_post_url');
  cj('.crm-smtp-form-block-subaccount').insertAfter('.crm-smtp-form-block-mandril_post_url');
  hideShow();
  cj('#smtpServer').blur(function() {
    hideShow();
  });
});
function hideShow() {
  if (cj('#smtpServer').val() == 'smtp.mandrillapp.com') {
     cj('.crm-smtp-form-block-mandril_post_url').show();
     cj('.crm-smtp-form-block-notify_group').show();
     cj('.crm-smtp-form-block-subaccount').show();
  }
  else {
     cj('.crm-smtp-form-block-mandril_post_url').hide();
     cj('.crm-smtp-form-block-notify_group').hide();
     cj('.crm-smtp-form-block-subaccount').hide();
  }
  
}
{/literal}
</script>
