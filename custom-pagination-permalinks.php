<?php
/*
Plugin Name: Custom Pagination Permalinks
Plugin URI: http://blogestudio.com
Description: Custom listing pagination URLs instead default WordPress permalinks like "[..]/page/[number]/"
Version: 1.0
Author: Pau Iglesias, Blogestudio
Text Domain: custom-pagination-permalinks
License: GPLv2 or later
*/

// Avoid script calls via plugin URL
if (!function_exists('add_action'))
	die;

/**
 * Custom Pagination Permalinks plugin class
 *
 * @package WordPress
 * @subpackage Custom Pagination Permalinks
 */

// Avoid declaration plugin class conflicts
if (!class_exists('BE_Custom_Pagination_Permalinks')) {
	
	// Create object plugin
	add_action('init', array('BE_Custom_Pagination_Permalinks', 'instance'));
	
	// Main class
	class BE_Custom_Pagination_Permalinks {



		// Constants and properties
		// ---------------------------------------------------------------------------------------------------



		// Plugin menu
		private $plugin_url;
		private $parent_slug;
		
		// Plugin title
		const title = 			'Custom Pagination Permalinks';
		const title_menu = 		'Custom Pagination Permalinks';
		
		// Slug
		const slug = 			'custom-pagination-permalinks';
		const parent = 			'options-general.php'; // 'settings.php'; // For network
		
		// Role
		const capability =  	'administrator';
		
		// Key prefix
		const key = 			'be_cpp_';
		
		// Translation
		const text_domain = 	'custom-pagination-permalinks';
		
		// Template var
		const numvar = 			'%number%';



		// Initialization
		// ---------------------------------------------------------------------------------------------------



		/**
		 * Creates a new object instance
		 */
		public static function instance() {
			return new BE_Custom_Pagination_Permalinks;
		}



		/**
		 * Constructor
		 */
		private function __construct() {
			
			// Rewrite rules
			add_filter('rewrite_rules_array', array('BE_Custom_Pagination_Permalinks', 'rewrite_rules_array'));
			
			// Admin area
			if (is_admin()) {
				
				// Admin menu
				add_action('admin_menu', array(&$this, 'admin_menu'));
				
			// Front-end
			} elseif (!(defined('DOING_AJAX') && DOING_AJAX)) {
				
				// Check permalink template
				if (self::is_active() && false !== self::get_suffix()) {
				
					// Permalinks generation
					add_filter('paginate_links', array(&$this, 'get_pagenum_link'));
					add_filter('get_pagenum_link', array(&$this, 'get_pagenum_link'));
					
					// All in One SEO Park plugin support
					add_filter('aioseop_canonical_url', array(&$this, 'canonical_url'));
					
					// Avoid WP canonical redirect
					add_filter('redirect_canonical', array(&$this,'redirect_canonical'), 10, 2);
					
					// Redirect from old /page/[number]/ permalinks
					add_action('wp', array(&$this, 'redirect_check'));
				}
				
				// Check rel next display
				if (get_option(self::key.'prevnext')) {
					
					// WP HTML head hook
					add_action('wp_head', array(&$this, 'prevnext'), 0);
				}
			}
		}



		/**
		 * Plugin activation hook
		 */
		public static function activation() {
			if (self::is_active() && false !== self::get_suffix()) {
				add_filter('rewrite_rules_array', array('BE_Custom_Pagination_Permalinks', 'rewrite_rules_array'));
				flush_rewrite_rules(false);
			}
		}



		/**
		 * Plugin deactivation hook
		 */
		public static function deactivation() {
			update_option(self::key.'active', '0');
			remove_filter('rewrite_rules_array', array('BE_Custom_Pagination_Permalinks', 'rewrite_rules_array'));
			flush_rewrite_rules(false);
		}



		/**
		 *  Load translation file
		 */
		private function load_plugin_textdomain($lang_dir = 'languages') {
			
			// Check load
			static $loaded;
			if (isset($loaded))
				return;
			$loaded = true;
			
			// Check if this plugin is placed in wp-content/mu-plugins directory or subdirectory
			if (('mu-plugins' == basename(dirname(__FILE__)) || 'mu-plugins' == basename(dirname(dirname(__FILE__)))) && function_exists('load_muplugin_textdomain')) {
				load_muplugin_textdomain(self::text_domain, ('mu-plugins' == basename(dirname(__FILE__))? '' : basename(dirname(__FILE__)).'/').$lang_dir);
			
			// Usual wp-content/plugins directory location
			} else {
				load_plugin_textdomain(self::text_domain, false, basename(dirname(__FILE__)).'/'.$lang_dir);
			}
		}



		// Replace Hooks
		// ---------------------------------------------------------------------------------------------------



		/**
		 * Custom paged link composition
		 */
		public function get_pagenum_link($uri) {
			
			// Check rewrite type
			global $wp_rewrite;
			if (!$wp_rewrite->using_permalinks())
				return $uri;
			
			// Initialize
			$args = '';
			$page_num = 0;
			
			// Check parts
			$parts = explode('?', $uri);
			if (count($parts) > 1) {
				$uri = trim($parts[0]);
				$args = trim($parts[1]);
			}
			
			// Remove WP pagination suffix
			if (1 == preg_match($this->get_old_pattern(true, 'i'), $uri, $matches)) {
				$page_num = (int) $matches[1];
				$uri = preg_replace($this->get_old_pattern(false, 'i'), '/', $uri);
			}
			
			// Remove forced pagination prefix
			$uri = preg_replace($this->get_new_pattern(false, 'i'), '/', $uri);
			
			// Add pagination number
			if ($page_num > 1)
				$uri .= str_ireplace(self::numvar, $page_num, ltrim(self::get_suffix(), '/'));
			
			// Done
			return empty($args)? $uri : rtrim($uri, '/').'/?'.$args;
		}



		/**
		 * Canonical conversion for paged requests
		 */
		public function canonical_url($uri) {
			return ((int) get_query_var('paged') > 1)? $this->get_pagenum_link($uri) : $uri;
		}



		/**
		 * Avoid WP canonical redirects
		 */
		public function redirect_canonical($redirect_url, $requested_url) {
			$uri = explode('?', $requested_url);
			if (1 == preg_match($this->get_new_pattern(false, 'i'), trim($uri[0]))) {
				$requested_check = $this->get_pagenum_link($redirect_url);
				return ($requested_check === $requested_url)? false : $requested_check;
			}
			return $redirect_url;
		}



		/**
		 * Alter rewrite rules
		 */
		public static function rewrite_rules_array($rules) {
			
			// Check status and suffix value
			if (!self::is_active() || false === ($suffix = self::get_suffix()))
				return $rules;
			
			// Check number variable and set pos var
			if (false === ($pos = stripos($suffix, self::numvar)))
				return $rules;
			
			// Explode suffix
			$suffix_ini = str_replace('/', '/?', str_replace('.', '\.', ltrim(substr($suffix, 0, $pos), '/')));
			$suffix_end = str_replace('/', '/?', str_replace('.', '\.', substr($suffix, $pos + 8)));
			
			// Initialize
			$new_rules = array();
			
			// Check current rules
			if (!empty($rules) && is_array($rules)) {
				
				// Globals
				global $wp_rewrite;
				
				// Enum current rules
				foreach ($rules as $key => $value) {
					
					// Home pagination
					if (0 === stripos($key, $wp_rewrite->pagination_base.'/?')) {
						$key = $suffix_ini.str_replace('/?$', '', substr($key, strlen($wp_rewrite->pagination_base.'/?'))).$suffix_end.'$';
						
					// Any paginated URL
					} elseif (false !== stripos($key, '/'.$wp_rewrite->pagination_base.'/?')) {
						$key = explode('/'.$wp_rewrite->pagination_base.'/?', $key);
						$key = $key[0].'/'.$suffix_ini.str_replace('/?$', '', $key[1]).$suffix_end.'$';
					}
					
					// Copy rule
					$new_rules[$key] = $value;
				}
			}
			
			// Result
			return $new_rules;
		}



		/**
		 * Redirect old /page/[number]/ URLs to new destiny
		 */
		public function redirect_check() {
			
			// Check valid context
			if (empty($_SERVER['REQUEST_URI']) || !self::is_active() || false === self::get_suffix())
				return;
			
			// Check expected part of current URI
			$uri = explode('?', $_SERVER['REQUEST_URI']);
			$args = (count($uri) > 1 && !empty($uri[1]))? $uri[1] : '';
			$uri = trim($uri[0]);
			if (empty($uri))
				return;
			
			// Singular cases
			if (is_singular()) {
				
				// Avoid singular pages with new pagination structure
				if (1 == preg_match($this->get_new_pattern(false, 'i'), $uri)) {
					
					// Globals
					global $multipage;
					
					// Avoid multipage conflicts
					if (!($multipage && '/'.self::numvar == rtrim($this->suffix, '/')))
						$this->redirect(get_permalink());
				}
				
			// Listings with new pagination structure but incorrect page number
			} elseif (1 == preg_match($this->get_new_pattern(true, 'i'), $uri, $matches)) {
				
				// Check bad page number
				if ((int) $matches[1] < 2) {
					$redirect_uri = preg_replace($this->get_new_pattern(false, 'i'), '/', $uri);
					$this->redirect(home_url($redirect_uri.(empty($args)? '' : '?'.$args)));
				}
			
			// Listings with classic pagination structure
			} elseif (1 == preg_match($this->get_old_pattern(false, 'i'), $uri)) {
				
				// Get permalink equivalence
				$redirect_uri = $this->get_pagenum_link($_SERVER['REQUEST_URI']);
				if (!empty($redirect_uri))
					$this->redirect(home_url($redirect_uri));
			}
		}



		/**
		 * Perform a redirection
		 */
		private function redirect($url, $status = 301) {
			
			// Remove any PHP header
			$headers = @headers_list();
			if (!empty($headers) && is_array($headers)) {
				foreach ($headers as $header) {
					list($k, $v) = array_map('trim', explode(':', $header, 2));
					@header($k.':');
				}
			}
			
			// And redirect
			wp_redirect($url, $status);
			die;
		}



		/**
		 * Check plugin activation
		 */
		private static function is_active() {
			return (1 == (int) get_option(self::key.'active'));
		}



		/**
		 * Return saved option
		 */
		private static function get_suffix() {
			$suffix = trim(ltrim(trim(get_option(self::key.'suffix')), '/'));
			return (empty($suffix) || (false === stripos($suffix, self::numvar)))? false : '/'.$suffix;
		}



		/**
		 * Compose pattern for new structure suffix matches
		 */
		private function get_new_pattern($page_number = false, $modifiers = '', $suffix = null) {
			return '/\/'.str_ireplace(self::numvar, $page_number? '(\d+)' : '\d+', str_replace('/', '\/', str_replace('.', '\.', trim((empty($suffix)? ''.self::get_suffix() : $suffix), '/')))).'\/?$/'.$modifiers;
		}



		/**
		 * The pattern for old pagination structure
		 */
		private function get_old_pattern($page_number = false, $modifiers = '') {
			global $wp_rewrite;
			return '/\/'.$wp_rewrite->pagination_base.'\/'.($page_number? '(\d+)' : '\d+').'\/?$/'.$modifiers;
		}



		/**
		 * Add prev/next  rel attribut tu link tag in header section
		 */
		public function prevnext() {
			
			// Globals
			global $paged;
			
			// Previous link
			if (get_previous_posts_link())
				echo "\t".'<link rel="prev" href="'.get_pagenum_link($paged - 1).'" />'."\n\n";
			
			// Next link
			if (get_next_posts_link())
				echo "\t".'<link rel="next" href="'.get_pagenum_link($paged + 1).'" />'."\n\n";
		}



		// Admin Page
		// ---------------------------------------------------------------------------------------------------



		/**
		 * Admin menu hook
		 */
		public function admin_menu() {
			$this->parent_slug = apply_filters(self::key.'parent_menu', self::parent, self::slug);
			$this->plugin_url = apply_filters(self::key.'plugin_url', admin_url(self::parent.'?page='.self::slug), $this->parent_slug);
			add_submenu_page($this->parent_slug, self::title, self::title_menu, self::capability, self::slug, array(&$this, 'admin_page'));
		}



		/**
		 * Admin page display
		 */
		public function admin_page() {
			
			// Check user capabilities
			if (!current_user_can(self::capability))
				wp_die(__('You do not have sufficient permissions to access this page.'));
				
			// Load translations
			$this->load_plugin_textdomain();
			
			// Initialize
			$error_number = false;
			$updated_permalink = false;
			$updated_linkrel = false;
			
			// Check submit
			if (isset($_POST['nonce'])) {
				
				// Check nonce
				if (!wp_verify_nonce($_POST['nonce'], __FILE__))
					return;
				
				// Check form sended
				if (!isset($_POST['form']) || ('permalink' != $_POST['form'] && 'linkrel' != $_POST['form']))
					return;
				
				// Process permalink request
				if ('permalink' == $_POST['form']) {
				
					// Check suffix
					$suffix = isset($_POST['tx-suffix'])? trim($_POST['tx-suffix']) : '';
					if (!empty($suffix)) {
						$suffix = '/'.ltrim($suffix, '/');
						if (false === stripos($suffix, self::numvar))
							$error_number = true;
					}
					
					// Check error
					if (!$error_number)
						$updated_permalink = true;
					
					// Update option values
					update_option(self::key.'suffix', $suffix);
					update_option(self::key.'active', ((empty($suffix) || $error_number)? '0' : (empty($_POST['sl-status'])? '0' : '1')));
					
					// Flush rewrite
					flush_rewrite_rules(false);
				
				// Process link rel request
				} elseif ('linkrel' == $_POST['form']) {
				
					// Update prev/next
					update_option(self::key.'prevnext', empty($_POST['ck-prevnext'])? 0 : 1);
					$updated_linkrel = true;
				}
			}
			
			?><div class="wrap">
			
				<?php screen_icon(); ?>
				
				<h2><?php echo self::title; ?></h2>
				
				<?php if ($updated_linkrel) : ?><div class="updated fade" style="margin: 15px 0 5px 0;"><p><?php _e('The option on the <b>tag link in head</b> has been updated.', self::text_domain); ?></p></div>
				
				<?php elseif ($updated_permalink) : ?><div class="updated fade" style="margin: 15px 0 5px 0;"><p><?php _e('The <b>pagination permalink</b> options has been updated.', self::text_domain); ?></p></div>
				
				<?php elseif ($error_number) : ?><div class="updated fade" style="margin: 15px 0 5px 0;"><p><?php _e('<b>Error</b>: the variable <b>%number%</b> is required in the pagination permalink.', self::text_domain); ?></p></div><?php endif; ?>
				
				<div id="poststuff">
					
					<div class="postbox">
						
						<h3 class="hndle"><span><?php _e('Pagination permalinks', self::text_domain); ?></span></h3>
						
						<div class="inside">
							
							<div id="postcustomstuff">
							
								<form method="post" action="<?php echo $this->plugin_url; ?>">
								
									<input type="hidden" name="nonce" value="<?php echo wp_create_nonce(__FILE__); ?>" />
									
									<input type="hidden" name="form" value="permalink" />
									
									<p><?php _e('Replaces the URLs like <code><small>/page/[number]/</small></code> in the pagination permalinks, you must use the mark <b><code><small>%number%</small></code></b> for numerical position in the string.', self::text_domain); ?></p>
									
									<p><label for="sl-status" style="display: inline-block; width: 200px; padding-top: 5px;"><?php _e('New permalinks status:', self::text_domain); ?></label><select id="sl-status" name="sl-status"><option value="0"><?php _e('Inactive', self::text_domain); ?></option><option <?php echo self::is_active()? 'selected' : ''; ?> value="1"><?php _e('Active', self::text_domain); ?></option></select></p>
									
									<p><label for="tx-suffix" style="display: inline-block; width: 200px; padding-top: 5px;"><?php _e('Pagination permalink suffix:', self::text_domain); ?></label><input type="text" id="tx-suffix" name="tx-suffix" value="<?php echo esc_attr(get_option(self::key.'suffix')); ?>" class="regular-text" /></p>
									
									<p><span style="display: inline-block; width: 200px;">&nbsp;</span><?php _e('Examples: <code><small>/page-%number%.html</small></code>, <code><small>/the-%number%th-page/</small></code>, <small>or</small> <code><small>/page-%number%/</small></code>', self::text_domain); ?></p>
									
									<p><span style="display: inline-block; width: 200px;">&nbsp;</span><input type="submit" value="<?php _e('Save changes', self::text_domain); ?>" class="button-primary" /></p>
									
									<h4><?php _e('Additional notes', self::text_domain); ?></h4>
									
									<ul>
										
										<li><?php printf(__('This plugin only works if you have activated the <a href="%s">pretty permalinks</a> of WordPress.', self::text_domain), admin_url('options-permalink.php')); ?></li>
										
										<li><?php _e('In search pages or URLs having arguments (sign ? in the URL), this plugin inserts a final slash (/) before the question mark.', self::text_domain); ?></li>
										
										<li><?php _e('The old Wordpress URLs like <code><small>/page/[number]/</small></code> automatically redirects to the new URLs created with this plugin.', self::text_domain); ?></li>
										
										<li><?php _e('Pay attention to your robots.txt file if you have disallowed URLs like <code><small>/page/[number]/</small></code>, because new permalinks can be accessible to search engines.', self::text_domain); ?></li>
										
										<li><?php _e('If you want to return to the original values set as Inactive the <i>New permalinks status</i> option.', self::text_domain); ?></li>
									
									</ul>
									
								</form>
							
							</div>
						
						</div>
						
					</div>
					
					<div class="postbox">
						
						<h3 class="hndle"><span><?php _e('Indicate paginated content', self::text_domain); ?></span></h3>
						
						<div class="inside">
							
							<div id="postcustomstuff">
							
								<form method="post" action="<?php echo $this->plugin_url; ?>">
								
									<input type="hidden" name="nonce" value="<?php echo wp_create_nonce(__FILE__); ?>" />
								
									<input type="hidden" name="form" value="linkrel" />
									
									<p><?php _e('In paginated listing pages adds the tag <code><small>link</small></code> in the internal <code><small>head</small></code> section.', self::text_domain); ?></p>
									
									<p><input type="checkbox" id="ck-prevnext" name="ck-prevnext" value="1" <?php echo get_option(self::key.'prevnext')? 'checked' : ''; ?> /><label for="ck-prevnext">&nbsp;<?php _e('Enable tags <code><small>link</small></code> with attributes <code><small>rel="prev"</small></code> and/or <code><small>rel="next"</small></code> in paginated listings.', self::text_domain); ?></label></p>
									
									<p><input type="submit" value="<?php _e('Save changes', self::text_domain); ?>" class="button-primary" /></p>
									
									<h4><?php _e('Additional notes', self::text_domain); ?></h4>
									
									<ul>
										
										<li><?php _e('Allows you to tell search engines that the current page is part of a single list.', self::text_domain); ?></li>
									
										<li><?php _e('More information about the paginated content on this page of <a href="https://support.google.com/webmasters/answer/1663744?hl=en" target="_blank">Google Webmaster Tools</a>', self::text_domain); ?></li>
									
									</ul>
							
								</form>
							
							</div>
						
						</div>
					
					</div>
				
				</div>
			
			</div><?php
		}



	}

	// Plugin activation
	register_activation_hook(__FILE__, 	 array('BE_Custom_Pagination_Permalinks', 'activation'));
	register_deactivation_hook(__FILE__, array('BE_Custom_Pagination_Permalinks', 'deactivation'));
}