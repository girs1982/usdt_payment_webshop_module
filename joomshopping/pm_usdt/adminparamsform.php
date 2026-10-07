<?php
defined('_JEXEC') or die();
use Joomla\CMS\HTML\HTMLHelper;
?>
<div class="col100">
<fieldset class="adminform">
<table class="admintable" width="100%">
 <tr>
   <td style="width:250px;" class="key">Gateway URL</td>
   <td><input type="text" class="inputbox form-control" name="pm_params[api_url]" size="60" value="<?php echo htmlspecialchars($params['api_url']); ?>" placeholder="http://192.168.80.200:8080" /></td>
 </tr>
 <tr>
   <td class="key">API key</td>
   <td><input type="text" class="inputbox form-control" name="pm_params[api_key]" size="60" value="<?php echo htmlspecialchars($params['api_key']); ?>" placeholder="token from gateway admin Settings" /></td>
 </tr>
 <tr>
   <td class="key">Paid status</td>
   <td><?php print HTMLHelper::_('select.genericlist', $orders->getAllOrderStatus(), 'pm_params[transaction_end_status]', 'class="inputbox custom-select" size="1"', 'status_id', 'name', $params['transaction_end_status']); ?></td>
 </tr>
 <tr>
   <td class="key">Pending status</td>
   <td><?php print HTMLHelper::_('select.genericlist', $orders->getAllOrderStatus(), 'pm_params[transaction_pending_status]', 'class="inputbox custom-select" size="1"', 'status_id', 'name', $params['transaction_pending_status']); ?></td>
 </tr>
 <tr>
   <td class="key">Failed status</td>
   <td><?php print HTMLHelper::_('select.genericlist', $orders->getAllOrderStatus(), 'pm_params[transaction_failed_status]', 'class="inputbox custom-select" size="1"', 'status_id', 'name', $params['transaction_failed_status']); ?></td>
 </tr>
</table>
</fieldset>
</div>
<div class="clr"></div>
