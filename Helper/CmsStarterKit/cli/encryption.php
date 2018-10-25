<?php

include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "GigyaApiHelper.php";

set_error_handler('exceptions_error_handler');

function get_available_commands() {
	return array(
		array(
			'command'     => 'e',
			'description' => 'Encrypt',
			'function'    => '\Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper::enc',
			'arg_count'   => 2,
			'check_args'  => false,
		),
		array(
			'command'     => 'd',
			'description' => 'Decrypt',
			'function'    => '\Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper::decrypt',
			'arg_count'   => 2,
			'check_args'  => false,
		),
		array(
			'command'     => 'gen',
			'description' => 'Generate key',
			'function'    => '\Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper::genKeyFromString',
			'arg_count'   => 1,
			'check_args'  => true,
		),
		array(
			'command'     => 'help',
			'description' => 'Help',
			'function'    => 'help',
			'arg_count'   => 1,
			'check_args'  => true,
		),
	);
}

/**
 * @param $severity
 * @param $message
 * @param $filename
 * @param $lineno
 *
 * @throws ErrorException
 */
function exceptions_error_handler($severity, $message, $filename, $lineno) {
	if (!error_reporting())
		return;

	if (error_reporting() & $severity)
		throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

function help() {
	$available_commands = get_available_commands();

	echo 'Available commands:' . PHP_EOL;
	foreach ($available_commands as $command_set)
	{
		echo '-' . $command_set['command'] . "\t" . $command_set['description'] . PHP_EOL;
	}
}

/**
 * @param $cli_args
 *
 * @return mixed
 */
function perform_key_operation_from_cli($cli_args) {
	$msg = 'Invalid command-line arguments provided. Type -help to see a list of commands.';

	if (empty($cli_args[1]) or $cli_args[1][0] !== '-')
	{
		throw new Error($msg);
	}
	else
	{
		$available_commands = get_available_commands();
		$command            = substr($cli_args[1], 1);
		$commands           = array_column($available_commands, 'command');

		if (in_array($command, $commands))
		{
			$command_key  = array_search($command, $commands);
			$full_command = $available_commands[$command_key];

			$arg_array = array_slice($cli_args, 2);
			if ($full_command['check_args'])
			{
				for ($i = 0; $i < $full_command['arg_count']; $i++)
				{
					if (!isset($arg_array[$i]))
						$arg_array[$i] = null;
				}
			}

			return call_user_func_array($full_command['function'], $arg_array);
		}
		else
		{
			throw new Error($msg);
		}
	}
}

try
{
	echo perform_key_operation_from_cli($argv) . PHP_EOL;
}
catch (Error $e)
{
	$stderr = fopen('php://stderr', 'w');
	fwrite($stderr, 'Error: ' . $e->getMessage() . PHP_EOL . 'Key not generated.');
	fclose($stderr);
}