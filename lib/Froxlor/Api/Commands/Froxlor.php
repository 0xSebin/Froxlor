<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, you can also view it online at
 * https://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  the authors
 * @author     Froxlor team <team@froxlor.org>
 * @license    https://files.froxlor.org/misc/COPYING.txt GPLv2
 */

namespace Froxlor\Api\Commands;

use Exception;
use Froxlor\Api\ApiCommand;
use Froxlor\Cron\TaskId;
use Froxlor\Database\Database;
use Froxlor\Database\IntegrityCheck;
use Froxlor\FroxlorLogger;
use Froxlor\Http\HttpClient;
use Froxlor\Settings;
use Froxlor\SImExporter;
use Froxlor\System\Cronjob;
use Froxlor\System\Crypt;
use Froxlor\UI\Response;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * @since 0.10.0
 */
class Froxlor extends ApiCommand
{

	/**
	 * checks whether there is a newer version of froxlor available
	 *
	 * @access admin
	 * @return string json-encoded array
	 * @throws Exception
	 */
	public function checkUpdate()
	{
		define('UPDATE_URI', "https://version.froxlor.org/Froxlor/api/" . $this->version);

		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {
			if (function_exists('curl_version')) {
				// log our actions
				$this->logger()->logAction(FroxlorLogger::ADM_ACTION, LOG_NOTICE, "[API] checking for updates");

				// check for new version
				try {
					$latestversion = HttpClient::urlGet(UPDATE_URI, true, 3);
				} catch (Exception $e) {
					$latestversion = \Froxlor\Froxlor::getVersion() . "|Version-check currently unavailable, please try again later";
				}
				$latestversion = explode('|', $latestversion);

				if (is_array($latestversion) && count($latestversion) >= 1) {
					$_version = $latestversion[0];
					$_message = isset($latestversion[1]) ? $latestversion[1] : '';
					$_link = isset($latestversion[2]) ? $latestversion[2] : '';

					// add the branding so debian guys are not gettings confused
					// about their version-number
					$version_label = $_version . $this->branding;
					$version_link = $_link;
					$message_addinfo = $_message;

					// not numeric -> error-message
					if (!preg_match('/^((\d+\\.)(\d+\\.)(\d+\\.)?(\d+)?(\-(svn|dev|rc)(\d+))?)$/', $_version)) {
						// check for customized version to not output
						// "There is a newer version of froxlor" besides the error-message
						$isnewerversion = -1;
					} elseif (\Froxlor\Froxlor::versionCompare2($this->version, $_version) == -1) {
						// there is a newer version - yay
						$isnewerversion = 1;
					} else {
						// nothing new
						$isnewerversion = 0;
					}

					// anzeige über version-status mit ggfls. formular
					// zum update schritt #1 -> download
					if ($isnewerversion == 1) {
						$text = 'There is a newer version available: "' . $_version . '" (Your current version is: ' . $this->version . ')';
						return $this->response([
							'isnewerversion' => $isnewerversion,
							'version' => $_version,
							'message' => $text,
							'link' => $version_link,
							'additional_info' => $message_addinfo
						]);
					} elseif ($isnewerversion == 0) {
						// all good
						return $this->response([
							'isnewerversion' => $isnewerversion,
							'version' => $version_label,
							'message' => "",
							'link' => $version_link,
							'additional_info' => $message_addinfo
						]);
					} else {
						Response::standardError('customized_version', '', true);
					}
				}
			}
			return $this->response([
				'isnewerversion' => 0,
				'version' => $this->version . $this->branding,
				'message' => 'Version-check not available due to missing php-curl extension',
				'link' => UPDATE_URI . '/pretty',
				'additional_info' => ""
			], 502);
		}
		throw new Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * import settings
	 *
	 * @param string $json_str
	 *            content of exported froxlor-settings json file
	 *
	 * @access admin
	 * @return string json-encoded bool
	 * @throws Exception
	 */
	public function importSettings()
	{
		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {
			$json_str = $this->getParam('json_str');
			$this->logger()->logAction(FroxlorLogger::ADM_ACTION, LOG_NOTICE, "User " . $this->getUserDetail('loginname') . " imported settings");
			try {
				SImExporter::import($json_str);
				Cronjob::inserttask(TaskId::REBUILD_VHOST);
				Cronjob::inserttask(TaskId::CREATE_QUOTA);
				// Using nameserver, insert a task which rebuilds the server config
				Cronjob::inserttask(TaskId::REBUILD_DNS);
				// cron.d file
				Cronjob::inserttask(TaskId::REBUILD_CRON);
				return $this->response(true);
			} catch (Exception $e) {
				throw new Exception($e->getMessage(), 406);
			}
		}
		throw new Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * export settings
	 *
	 * @access admin
	 * @return string json-string
	 * @throws Exception
	 */
	public function exportSettings()
	{
		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {
			$this->logger()->logAction(FroxlorLogger::ADM_ACTION, LOG_NOTICE, "User " . $this->getUserDetail('loginname') . " exported settings");
			$json_export = SImExporter::export();
			return $this->response($json_export);
		}
		throw new Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * return a list of all settings
	 *
	 * @access admin
	 * @return string json-encoded array count|list
	 * @throws Exception
	 */
	public function listSettings()
	{
		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {
			$sel_stmt = Database::prepare("
				SELECT * FROM `" . TABLE_PANEL_SETTINGS . "` ORDER BY settinggroup ASC, varname ASC
			");
			Database::pexecute($sel_stmt, null, true, true);
			$result = [];
			while ($row = $sel_stmt->fetch(PDO::FETCH_ASSOC)) {
				$result[] = [
					'key' => $row['settinggroup'] . '.' . $row['varname'],
					'value' => $row['value']
				];
			}
			return $this->response([
				'count' => count($result),
				'list' => $result
			]);
		}
		throw new Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * return a setting by settinggroup.varname couple
	 *
	 * @param string $key
	 *            settinggroup.varname couple
	 *
	 * @access admin
	 * @return string
	 * @throws Exception
	 */
	public function getSetting()
	{
		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {
			$setting = $this->getParam('key');
			return $this->response(Settings::Get($setting));
		}
		throw new Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * updates a setting
	 *
	 * @param string $key
	 *            settinggroup.varname couple
	 * @param string $value
	 *            optional the new value, default is ''
	 *
	 * @access admin
	 * @return string
	 * @throws Exception
	 */
	public function updateSetting()
	{
		// currently not implemented as it requires validation too so no wrong settings are being stored via API
		throw new Exception("Not available yet.", 501);

		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {
			$setting = $this->getParam('key');
			$value = $this->getParam('value', true, '');
			$oldvalue = Settings::Get($setting);
			if (is_null($oldvalue)) {
				throw new Exception("Setting '" . $setting . "' could not be found");
			}
			$this->logger()->logAction(FroxlorLogger::ADM_ACTION, LOG_WARNING, "[API] Changing setting '" . $setting . "' from '" . $oldvalue . "' to '" . $value . "'");
			return $this->response(Settings::Set($setting, $value, true));
		}
		throw new Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * returns a random password based on froxlor settings for min-length, included characters, etc.
	 *
	 * @access admin, customer
	 * @return string
	 */
	public function generatePassword()
	{
		return $this->response(Crypt::generatePassword());
	}

	/**
	 * can be used to remotely run the integritiy checks froxlor implements
	 *
	 * @access admin
	 * @return string
	 * @throws Exception
	 */
	public function integrityCheck()
	{
		if ($this->isAdmin() && $this->getUserDetail('change_serversettings')) {
			$integrity = new IntegrityCheck();
			$result = $integrity->checkAll();
			if ($result) {
				return $this->response(null, 204);
			}
			throw new Exception("Some checks failed.", 406);
		}
		throw new Exception("Not allowed to execute given command.", 403);
	}

	/**
	 * returns a list of all available api functions
	 *
	 * @param string $module
	 *            optional, return list of functions for a specific module
	 * @param string $function
	 *            optional, return parameter information for a specific module and function
	 *
	 * @access admin, customer
	 * @return string json-encoded array
	 * @throws Exception
	 */
	public function listFunctions()
	{
		$module = $this->getParam('module', true, '');
		$function = $this->getParam('function', true, '');

		$functions = [];
		if ($module != null) {
			// check existence
			$this->requireModules($module);
			// now get all static functions
			$reflection = new ReflectionClass(__NAMESPACE__ . '\\' . $module);
			$_functions = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
			foreach ($_functions as $func) {
				if (empty($function) || ($function != null && $func->name == $function)) {
					if ($func->class == __NAMESPACE__ . '\\' . $module && $func->isPublic()) {
						array_push($functions, array_merge([
							'module' => $module,
							'function' => $func->name
						], $this->getParamListFromDoc($module, $func->name)));
					}
				}
			}
		} else {
			// check all the modules
			$path = \Froxlor\Froxlor::getInstallDir() . '/lib/Froxlor/Api/Commands/';
			// valid directory?
			if (is_dir($path)) {
				// create RecursiveIteratorIterator
				$its = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
				// check every file
				foreach ($its as $it) {
					// does it match the Filename pattern?
					$matches = [];
					if (preg_match("/^(.+)\.php$/i", $it->getFilename(), $matches)) {
						// check for existence
						try {
							// set the module to be in our namespace
							$mod = $matches[1];
							$this->requireModules($mod);
						} catch (Exception $e) {
							// @todo log?
							continue;
						}
						// now get all static functions
						$reflection = new ReflectionClass(__NAMESPACE__ . '\\' . $mod);
						$_functions = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
						foreach ($_functions as $func) {
							if ($func->class == __NAMESPACE__ . '\\' . $mod && $func->isPublic() && !$func->isStatic()) {
								array_push($functions, array_merge([
									'module' => $matches[1],
									'function' => $func->name
								], $this->getParamListFromDoc($matches[1], $func->name)));
							}
						}
					}
				}
			} else {
				// yikes - no valid directory to check
				throw new Exception("Cannot search directory '" . $path . "'. No such directory.", 500);
			}
		}

		// return the list
		return $this->response($functions);
	}

	/**
	 * this functions is used to check the availability
	 * of a given list of modules.
	 * If either one of
	 * them are not found, throw an Exception
	 *
	 * @param string|array $modules
	 *
	 * @throws Exception
	 */
	private function requireModules($modules = null)
	{
		if ($modules != null) {
			// no array -> create one
			if (!is_array($modules)) {
				$modules = [
					$modules
				];
			}
			// check all the modules
			foreach ($modules as $module) {
				try {
					$module = __NAMESPACE__ . '\\' . $module;
					// can we use the class?
					if (class_exists($module)) {
						continue;
					} else {
						throw new Exception('The required class "' . $module . '" could not be found but the module-file exists', 404);
					}
				} catch (Exception $e) {
					// The autoloader will throw an Exception
					// that the required class could not be found
					// but we want a nicer error-message for this here
					throw new Exception('The required module "' . $module . '" could not be found', 404);
				}
			}
		}
	}

	/**
	 * generate an api-response to list all parameters and the return-value of
	 * a given module.function-combination
	 *
	 * @param string $module
	 * @param string $function
	 *
	 * @return array|bool
	 * @throws Exception
	 */
	private function getParamListFromDoc($module = null, $function = null)
	{
		try {
			// set the module
			$cls = new ReflectionMethod(__NAMESPACE__ . '\\' . $module, $function);
			$comment = $cls->getDocComment();
			if ($comment == false) {
				return [
					'head' => 'There is no comment-block for "' . $module . '.' . $function . '"'
				];
			}

			$clines = explode("\n", $comment);
			$result = [];
			$result['params'] = [];
			$param_desc = false;
			$r = [];
			foreach ($clines as $c) {
				$c = trim($c);
				// check param-section
				if (strpos($c, '@param')) {
					preg_match('/^\*\s\@param\s(.+)\s(\$\w+)(\s.*)?/', $c, $r);
					// cut $ off the parameter-name as it is not wanted in the api-request
					$result['params'][] = [
						'parameter' => substr($r[2], 1),
						'type' => $r[1],
						'desc' => (isset($r[3]) ? trim($r['3']) : '')
					];
					$param_desc = true;
				} elseif (strpos($c, '@access')) {
					// check access-section
					preg_match('/^\*\s\@access\s(.*)/', $c, $r);
					if (!isset($r[0]) || empty($r[0])) {
						$r[1] = 'This function has no restrictions';
					}
					$result['access'] = [
						'groups' => (isset($r[1]) ? trim($r[1]) : '')
					];
				} elseif (strpos($c, '@return')) {
					// check return-section
					preg_match('/^\*\s\@return\s(\w+)(\s.*)?/', $c, $r);
					if (!isset($r[0]) || empty($r[0])) {
						$r[1] = 'null';
						$r[2] = 'This function has no return value given';
					}
					$result['return'] = [
						'type' => $r[1],
						'desc' => (isset($r[2]) ? trim($r[2]) : '')
					];
				} elseif (!empty($c) && strpos($c, '@throws') === false) {
					// check throws-section
					if (substr($c, 0, 3) == "/**") {
						continue;
					}
					if (substr($c, 0, 2) == "*/") {
						continue;
					}
					if (substr($c, 0, 1) == "*") {
						$c = trim(substr($c, 1));
						if (empty($c)) {
							continue;
						}
						if ($param_desc) {
							$result['params'][count($result['params']) - 1]['desc'] .= $c;
						} else {
							if (!isset($result['head']) || empty($result['head'])) {
								$result['head'] = $c . " ";
							} else {
								$result['head'] .= $c . " ";
							}
						}
					}
				}
			}
			$result['head'] = trim($result['head']);
			return $result;
		} catch (ReflectionException $e) {
			return [];
		}
	}
}
