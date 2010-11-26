<?php
/*
Plugin Name: TwitterCount
Description: A well-optimized and reliable plugin that connects to the Twitter API to retrieve your followers count, that you can print out in plain text.
Author: Artemev Yurii
Author URI: http://artemeff.ru
Plugin URI: http://artemeff.ru/twittercounter-prostoj-schetchik-follouverov.html
Version: 0.2
*/

/**
 * TwitterCount
 *
 * @author Artemev Yurii
 * @version $Id$
 * @copyright Artemev, 26 November, 2010
 * @package Twittercount
 **/

class TwitterCount {
	
	/**
	 * Constructor
	 *
	 * @return void
	 * @author Artemev Yurii
	 */
	function TwitterCount(){		
		if ($this->isSetup()){
			$this->retrieve();
			$this->count = get_option('twt_count');	
		} else {
			$this->count = '<!-- Plugin not setup -->';
		}
		
		add_action('activate_twittercount.php', array(&$this, 'onActivate'));
		add_action('deactivate_twittercount.php', array(&$this, 'onDeactivate'));		
		add_action('admin_menu', array(&$this, 'onMenuConfigure'));
	}
	
	/**
	 * Called when the plugin is activated
	 *
	 * @return void
	 * @author Artemev Yurii
	 */
	function onActivate(){
		$olfb = get_option('twitter_settings');
		
		add_option('twt_uri', ($olfb && isset($olfb['twitter_url'])) ? str_replace('http://twitter.com/', '', $olfb['twitter_url']) : '');
		add_option('twt_count', '');
		add_option('twt_fallback_text', '');
		add_option('twt_every', '3 hours');
		add_option('twt_last_checked', '');
		add_option('twt_average_timeago', '');
	}

	/**
	 * Called when the plugin is deactivated
	 *
	 * @return void
	 * @author Artemev Yurii
	 */
	function onDeactivate()
	{
		delete_option('twt_uri');
		delete_option('twt_count');
		delete_option('twt_fallback_text');
		delete_option('twt_every');
		delete_option('twt_last_checked');
		delete_option('twt_average_timeago');
	}
	
	/**
	 * Configures the admin menu
	 *
	 * @return void
	 * @author Artemev Yurii
	 */
	function onMenuConfigure()
	{
		add_submenu_page('options-general.php', 'Twitter Count', 'TwitterCount', 8, __FILE__, array(&$this, 'administrate'));		
	}
	
	/**
	 * Determines if the plugin is ready to work
	 *
	 * @return boolean $setup
	 * @author Artemev Yurii
	 */
	function isSetup()
	{
		return !!get_option('twt_uri');
	}
	
	/**
	 * Generates a time in seconds from a string (1 hour => 3600)
	 *
	 * @param string $text 
	 * @return integer $interval
	 * @author Artemev Yurii
	 */
	function toSeconds($text)
	{
		if (is_numeric($text)) return $text;
		$text = strtotime('+' . ltrim($text, '+-'), 0);
		return $text > 0 ? $text : null;
	}
	
	/**
	 * Returns the arithmetic mean of the array values
	 *
	 * @param string $values 
	 * @return integer $mean
	 * @author Artemev Yurii
	 */
	function arrayMean($values, $round = true)
	{
		if (!sizeof($values)) return 0;
		$result = array_sum($values) / sizeof($values);
		return ($round) ? round($result) : $result;
	}
	
	/**
	 * Returns the Twitter URI to make the request
	 *
	 * @return string $uri
	 * @author Artemev Yurii
	 */
	function getTwitterUri()
	{
		$uri = 'http://twitter.com/users/show/' . urlencode(get_option('twt_uri'));
		
		if (get_option('twt_average_timeago'))
			$uri .= '&dates=' . urlencode(date('Y-m-d', time() - $this->toSeconds(get_option('twt_average_timeago'))) . ',' . date('Y-m-d'));
		
		return $uri;
	}
	
	/**
	 * Checks if it should retrieve now
	 *
	 * @return boolean $check
	 * @author Artemev Yurii
	 */
	function checkRetrieve()
	{
		if (!get_option('twt_every') || !get_option('twt_last_checked')) return true;
		if ((time() - $this->toSeconds(get_option('twt_every'))) > get_option('twt_last_checked')) return true;				
		return false;
	}
	
	/**
	 * Retrieves the count from Twitter with Snoopy
	 *
	 * @return boolean $force Force to retrieve
	 * @author Artemev Yurii
	 */
	function retrieve($force = false)
	{
		if ($force || $this->checkRetrieve())
		{
			update_option('twt_last_checked', time());
			$count = null;
			$response = '';
			
			if (function_exists('wp_remote_get')){
				$response = @wp_remote_get($this->getTwitterUri());
				$response = (is_array($response) && isset($response['body'])) ? $response['body'] : false;
			} else {
				$handler = @fopen($this->getTwitterUri(), 'r');
				if ($handler){
					while (!feof($handler)) $response .= fgets($handler);
					fclose($handler);
				}
			}
			
			if ($response){
				preg_match_all('|<followers_count>(.*)</followers_count>|U', $response, $values);
				if (!is_array($values) || !isset($values[1]) || !$values[1]) $count = null;
				else $count = $this->arrayMean($values[1]);	
			}

			$this->setCount($count);			
		}
	}
	
	/**
	 * Updates the count variable. If the supplied value is not valid and there's a feedback text, 
	 * it's updated to the feedback text. If not, no update takes place, resorting to the last valid value.
	 *
	 * @param string $count 
	 * @param string $update 
	 * @return void
	 * @author Artemev Yurii
	 */
	function setCount($count, $update = true){
		$count = (is_numeric($count) && $count > 0) ? $count : null; 
		if (is_null($count))
			$update = !!($count = get_option('twt_fallback_text'));				
		if ($update){
			$this->count = $count;
			update_option('twt_count', $count);
		} 
	}
	
	/**
	 * Called when user goes to options page
	 *
	 * @return void
	 * @author Artemev Yurii
	 */
	function administrate()
	{
		if (isset($_GET['force']) && $_GET['force']) $this->retrieve(true);
		
		$data = array();
		$errors = array();
		
		if (isset($_POST['twt_options'])){
			$errors = array();
			$data = $_POST['twt_options'];
			$data['fallback_text'] = $_POST['twt_unavailable'] == 'text' ? $data['fallback_text'] : '';
			$data['average_timeago'] = (isset($_POST['twt_average']) && $_POST['twt_average']) ? $data['average_timeago'] : '';
			
			if (!$data['uri']) $errors[] = 'The Twitter identifier is required';
			if (!$this->toSeconds($data['every'])) $errors[] = 'Revise the checking frequency';
			if (isset($_POST['twt_average']) && $_POST['twt_average'] && !$this->toSeconds($data['average_timeago'])) 
				$errors[] = 'Revise the average calculation date setting';			
			if (!sizeof($errors)) {
				if (!$this->isSetup()) {
					update_option('twt_count', '<!-- Awaiting first fetch -->');
					$this->retrieve(true);
				}
				foreach ($data as $key => $value) update_option('twt_' . $key, $value);
			}
		}		
		?>
		<div class="wrap">
		<div id="icon-tools" class="icon32"><br /></div>
		<h2>TwitterCount</h2>
		<p>Для вставки счетчика в шаблон, используйте следующий код <code>&lt;?php echo twt_count() ?&gt;</code></p>		
		
		<h3>Текущие значения</h3>	
		<table class="form-table">
			<tr>
				<th>Количество фоллоуверов</th>
				<td><code><?php echo htmlentities(twt_count()) ?></code> <a href="options-general.php?page=<?php echo plugin_basename(__FILE__) ?>&amp;force=true" class="button">Обновить</a></td>
			</tr>
			
			<tr>
				<th>Последняя проверка</th>
				<td><?php echo get_option('twt_last_checked') ? date('F j, Y, H:i:s', get_option('twt_last_checked')) : 'Никогда' ?></td>
			</tr>
			
			<tr>
				<th>Сгенерированный API URI</th>
				<td><code><?php echo $this->getTwitterUri() ?></code></td>
			</tr>
		</table>
		
		<h3>Настройки</h3>
		<?php if ($errors): ?>
		<div class="error">
			<?php foreach($errors as $error): ?>
			<p><?php echo $error ?></p>
			<?php endforeach ?>
		</div>
		<?php endif ?>
		<form action="options-general.php?page=<?php echo plugin_basename(__FILE__) ?>" method="post" accept-charset="utf-8">
			<table class="form-table">
				<tr>
					<th><label for="twt_options_uri">Ваш Twitter</label></th>
					<td><input type="text" name="twt_options[uri]" value="<?php echo isset($data['uri']) ? $data['uri'] : get_option('twt_uri') ?>" id="twt_options_uri"></td>
				</tr>
				<tr>
					<th><label for="twt_options_every">Обновлять каждые</label></th>
					<td><input type="text" name="twt_options[every]" value="<?php echo isset($data['every']) ? $data['every'] : get_option('twt_every') ?>" id="twt_options_every" /> <br />(пример: <strong>1 hour</strong>, <strong>2 days</strong>, <strong>4 weeks</strong>)</td>
				</tr>
				<!--<tr>
					<th><label for="twt_average">Расчитать среднее количество</label></th>
					<td><input type="checkbox" name="twt_average" value="1" <?php if((isset($_POST['twt_average']) && $_POST['twt_average']) || get_option('twt_average_timeago')) echo 'checked="checked"'; ?> id="twt_average"> <label for="twt_average_timeago">of the last</label> <input type="text" name="twt_options[average_timeago]" value="<?php echo isset($data['average_timeago']) ? $data['average_timeago'] : get_option('twt_average_timeago') ?>" id="twt_average_timeago" /><br />Use a number of seconds or a valid <a href="http://php.net/strtotime">string</a> (examples: <strong>15 days</strong>, <strong>2 months</strong>, <strong>4 weeks</strong>, not using words for numbers)</td>
				</tr>-->
				<tr>
					<th>Если недоступен API сервер</th>
					<td><input type="radio" name="twt_unavailable" value="last" id="twt_unavailable_last" <?php if((isset($_POST['twt_unavailable']) && $_POST['twt_unavailable'] == 'last') || !get_option('twt_fallback_text')) echo 'checked="checked"' ?> /> <label for="twt_unavailable_last">использовать последний результат</label><br />
							<input type="radio" name="twt_unavailable" value="text" id="twt_unavailable_text" <?php if((isset($_POST['twt_unavailable']) && $_POST['twt_unavailable'] == 'text') || get_option('twt_fallback_text')) echo 'checked="checked"' ?> /> <label for="twt_unavailable_text">использовать это: </label>
							<input type="text" name="twt_options[fallback_text]" value="<?php echo isset($data['fallback_text']) ? $data['fallback_text'] : get_option('twt_fallback_text') ?>" id="twt_options_fallback_text" />
					</td>
				</tr>
			</table>
			<p><input type="submit" class="button" value="Сохранить" /></p>
		</form>
		</div>
		<?php
	}
	
}

/**
 * TwitterCount instance
 *
 * @author Artemev Yurii
 */
global $TwitterCount;
$TwitterCount = new TwitterCount();

/**
 * Handy function to get the count quickly
 *
 * @return void
 * @author Artemev Yurii
 */
function twt_count(){
	global $TwitterCount;
	return $TwitterCount->count;
}
?>