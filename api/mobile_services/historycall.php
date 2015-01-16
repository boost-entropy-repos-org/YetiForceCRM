<?php
/*+***********************************************************************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com. 
 * All Rights Reserved.
 *************************************************************************************************************************************/
class HistoryCall{
    public $restler;
	public $userID;
	public $debug = true;
	public $permittedActions = array('addCallLogs');
	public $types = array(
		'OUTGOING' => 'Outgoing',
		'INCOMING' => 'Incoming'
	);
	public $status = array(
		'OUTGOING' => 'stat1',
		'INCOMING' => 'stat2'
	);
	
    function post($type = '', $authorization = '', $data = ''){
		$authorization = json_decode($authorization);
		global $log,$adb;
		$log->info("Start HistoryCall metod");
		if( $authorization->phoneKey == '' || !$this->checkPermissions($authorization) ){
			$resultData = Array('status' => 0,'message' =>  'No permission to: HistoryCall');
		}elseif( in_array($type,$this->permittedActions) ){
			$resultData = $this->$type($data);
		}else{
			$resultData = Array('status' => 0,'message' =>  'Method not found: '.$type);
		}
		if($this->debug){
			$file = 'api/mobile_services_HistoryCall_logs.txt';
			$dane = print_r( $resultData ,true);
			file_put_contents($file,'-----> '.date("Y-m-d H:i:s").' <-----'.PHP_EOL.$dane.PHP_EOL,FILE_APPEND | LOCK_EX);
		}
        return $test;
    }
	
	function addCallLogs($data){
		global $log,$adb;
		include_once 'modules/Users/Users.php';
		include_once 'modules/CallHistory/CallHistory.php';
		$log->info("Start HistoryCall::addCallLogs | user id: ".$this->userID);
		$resultData = array('status' => 2);
		$user = new Users();
		$count = 0;
		$current_user = $user->retrieveCurrentUserInfoFromFile(Users::getActiveAdminId());
		$data = json_decode($data);

		foreach ($data->callLogs as $call) {
			$to_number = $call->number;
			$from_number = $data->phoneNumber;
			$destination = $this->findPhoneNumber($to_number);
			
			$CallHistory = CRMEntity::getInstance('CallHistory');
			$CallHistory->column_fields['assigned_user_id'] =  $this->userID;
			$CallHistory->column_fields['callhistorytype'] = $this->getType( $call->type_text );
			$CallHistory->column_fields['callhistorystatus'] = $this->getStatus( $call->type_id );
			$CallHistory->column_fields['country'] = $call->country_iso;
			$CallHistory->column_fields['to_number'] = $to_number;
			$CallHistory->column_fields['from_number'] = $from_number;
			$CallHistory->column_fields['location'] = $call->geocoded_location;
			$CallHistory->column_fields['phonecallid'] = $call->id;
			$CallHistory->column_fields['start_time'] = $call->call_date;
			$CallHistory->column_fields['duration'] = $call->duration;
			$CallHistory->column_fields['imei'] = $data->imei;
			$CallHistory->column_fields['ipAddress'] = $data->ipAddress;
			$CallHistory->column_fields['simSerial'] = $data->simSerial;
			$CallHistory->column_fields['subscriberId'] = $data->subscriberId;
			if($destination)
				$CallHistory->column_fields['destination'] = $destination;
			$CallHistory->save('CallHistory');
			$count++;
		}
		$resultData = array('status' => 1, 'restStatus' => 'true', 'count' => $count);
		$log->info("End HistoryCall::addCallLogs | return: ".print_r( $resultData,true));
		return $resultData;
	}
	
	function checkPermissions($authorization){
		global $log,$adb;
		$log->info("Start HistoryCall::checkPermissions | ".print_r( $authorization,true));
		$return = false;	
		$result = $adb->pquery("SELECT yetiforce_mobile_keys.user FROM yetiforce_mobile_keys INNER JOIN vtiger_users ON vtiger_users.id = yetiforce_mobile_keys.user WHERE service = ? AND `key` = ? AND vtiger_users.user_name = ?",array('callhistory', $authorization->phoneKey, $authorization->userName),true);
		if($adb->num_rows($result) > 0 ){
			$this->userID = $adb->query_result_raw($result, 0, 'user');
			$return = true;	
		}
		$log->info("End HistoryCall::checkPermissions | return: ".$return);
		return $return;
	}
	
	function findPhoneNumber($number){
		global $log,$adb;
		$crmid = false;
		$modulesInstance = array();
		$sql = "SELECT columnname,tablename,vtiger_tab.name FROM vtiger_field INNER JOIN vtiger_tab ON vtiger_tab.tabid = vtiger_field.tabid WHERE uitype = '11' AND vtiger_tab.name IN ('Contacts','Accounts','Leads','OSSEmployees','Vendors')";
		$result = $adb->query($sql,true);
		for($i = 0; $i < $adb->num_rows($result); $i++){
			$module = $adb->query_result_raw($result, $i, 'name');
			$columnname = $adb->query_result_raw($result, $i, 'columnname');
			$tablename = $adb->query_result_raw($result, $i, 'tablename');
			if(!$modulesInstance[$module]){
				include_once 'modules/'.$module.'/'.$module.'.php';
				$moduleInstance = CRMEntity::getInstance($module);
				$modulesInstance[$module] = $moduleInstance->tab_name_index;
			}
			$table_index = $modulesInstance[$module][$tablename];
			$sqlNumber = '';
			foreach (str_split($number) as $num) {
				$sqlNumber .= '[^0-9]*'.$num;
			}
			$sql = "SELECT crmid FROM $tablename INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = $tablename.$table_index WHERE $columnname RLIKE '$sqlNumber';";
			$resultData = $adb->query($sql,true);
			if($adb->num_rows($resultData) > 0 ){
				$crmid = $adb->query_result_raw($resultData, 0, 'crmid');
				break;
			}
		}
		return $crmid;
	}
	function getType($type){
		return !$this->types[$type]? $type : $this->types[$type];
	}
	function getStatus($status){
		return !$this->status[$status]? $status : $this->status[$status];
	}
}