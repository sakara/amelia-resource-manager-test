<?php
namespace AmeliaResourceManager;

class Core
{
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $security_manager; // Aggiungi questa linea

    public function __construct()
    {
        $this->plugin_name = "amelia-resource-manager";
        $this->version = ARM_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies()
    {
        require_once ARM_PLUGIN_DIR . "includes/class-loader.php";
        require_once ARM_PLUGIN_DIR . "includes/class-security-manager.php";
        require_once ARM_PLUGIN_DIR . "includes/class-resource-manager.php";
        require_once ARM_PLUGIN_DIR . "includes/class-price-manager.php";

        $this->loader = new Loader();
        $this->security_manager = new SecurityManager(); // Inizializzazione SecurityManager
    }

    private function init_security()
    {
        $this->security_manager = new SecurityManager();
    }

    private function set_locale()
    {
        add_action("plugins_loaded", function () {
            load_plugin_textdomain(
                "amelia-resource-manager",
                false,
                dirname(plugin_basename(__FILE__)) . "/languages/"
            );
        });
    }

    private function define_admin_hooks()
    {
        $resource_manager = new ResourceManager($this->security_manager);
        $price_manager = new PriceManager($this->security_manager);

        // Aggiungi questi hook AJAX prima degli altri hook
        add_action('wp_ajax_save_resource_price', [$price_manager, 'save_resource_price']);
        add_action('wp_ajax_get_resource_price', [$price_manager, 'get_resource_price']);

        $this->loader->add_action(
            "admin_menu",
            $resource_manager,
            "add_menu_pages"
        );
        $this->loader->add_action(
            "admin_enqueue_scripts",
            $resource_manager,
            "enqueue_styles"
        );
        $this->loader->add_action(
            "admin_enqueue_scripts",
            $resource_manager,
            "enqueue_scripts"
        );
    }

    private function define_public_hooks()
    {
        // Hook per il frontend
    }

    public function run()
    {
        $this->loader->run();
    }

    public function get_security_manager()
    {
        return $this->security_manager;
    }
}
