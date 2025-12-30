<?php
/**
 * Autoplugin Generator class.
 *
 * @package WP-Autoplugin
 * @since 1.0.0
 * @version 1.0.5
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Generator class.
 */
class Plugin_Generator {

    /**
     * AI API in use.
     *
     * @var API
     */
    private $ai_api;

    /**
     * Constructor.
     *
     * @param API $ai_api The AI API in use.
     */
    public function __construct( $ai_api ) {
        $this->ai_api = $ai_api;
    }

    /**
     * Prompt the AI to generate a plan for a WordPress plugin.
     *
     * @param string $input The plugin features.
     *
     * @return string|WP_Error
     */
    public function generate_plugin_plan( $input, $prompt_images = [] ) {
        $plugin_mode = get_option( 'wp_autoplugin_plugin_mode', 'simple' );

        if ( 'complex' === $plugin_mode ) {
            return $this->generate_complex_plugin_plan( $input, $prompt_images );
        } else {
            return $this->generate_simple_plugin_plan( $input, $prompt_images );
        }
    }

    /**
     * Generate a plan for a simple single-file plugin.
     *
     * @param string $input The plugin features.
     *
     * @return string|WP_Error
     */
    private function generate_simple_plugin_plan( $input, $prompt_images = [] ) {
        $prompt = <<<PROMPT
            Generate a detailed technical specification and development plan for a WordPress plugin with the following features:

            ```
            $input
            ```

            The plugin must be contained within a single file, including all necessary code. Do not write the actual plugin code. Your response should be a valid JSON object, with clear and concise text in each of the following sections:

            - plugin_name: Provide a concise name for the plugin.
            - design_and_architecture: Outline the overall design and architecture, prioritizing security layers (Data Validation -> Processing -> Escaping).
            - detailed_feature_description: Detailed description of features and their secure implementation.
            - user_interface: Describe the UI elements and interaction.
            - security_considerations: MANDATORY: Define specific WordPress security functions to be used (e.g., check_admin_referer, sanitize_text_field, wp_kses).
            - testing_plan: Outline a plan for testing. Explain how the plugin works for non-technical users.

            Do not add any additional commentary. Make sure your response only contains a valid JSON object. Do not use Markdown formatting.
            PROMPT;

        $params  = [ 'response_format' => [ 'type' => 'json_object' ] ];
        $payload = AI_Utils::get_multimodal_payload( $this->ai_api, $prompt, $prompt_images );
        if ( ! empty( $payload ) ) {
            $params = array_merge( $params, $payload );
        }

        return $this->ai_api->send_prompt( $prompt, '', $params );
    }

    /**
     * Generate a plan for a complex multi-file plugin.
     *
     * @param string $input The plugin features.
     *
     * @return string|WP_Error
     */
    private function generate_complex_plugin_plan( $input, $prompt_images = [] ) {
        $prompt = <<<PROMPT
            Generate a detailed technical specification and development plan for a WordPress plugin with the following features:
            
            ```
            $input
            ```
            
            This will be a complex multi-file plugin. Do not write the actual plugin code. Your response should be a valid JSON object, with clear and concise text in each of the following sections:
            
            - plugin_name: Provide a concise name for the plugin.
            - design_and_architecture: Outline architecture including data flow and major components.
            - detailed_feature_description: Description of each feature and its technical implementation.
            - user_interface: Describe UI elements.
            - user_flows: Sequence of actions for key tasks.
            - security_considerations: MANDATORY: Outline a robust security concept including Nonces, Capability Checks (current_user_can), and strict Output Escaping (XSS prevention).
            - testing_plan: Clearly explain how the plugin functions.
            - project_structure: Define file and directory structure (PHP, CSS, JS only).
            
            IMPORTANT GUIDELINES:
            - Ensure all data handling follows the principle of "Sanitize on Input, Escape on Output".
            - Avoid over-engineering but never sacrifice security.
            - The plugin must be self-contained.
            
            Do not add any additional commentary. Make sure your response only contains a valid JSON object. Do not use Markdown formatting.
            PROMPT;

        $params  = [ 'response_format' => [ 'type' => 'json_object' ] ];
        $payload = AI_Utils::get_multimodal_payload( $this->ai_api, $prompt, $prompt_images );
        if ( ! empty( $payload ) ) {
            $params = array_merge( $params, $payload );
        }

        return $this->ai_api->send_prompt( $prompt, '', $params );
    }

    /**
     * Prompt the AI to generate a WordPress plugin code based on a plan.
     *
     * @param string $plan The plugin plan.
     *
     * @return string|WP_Error
     */
    public function generate_plugin_code( $plan ) {
        $plugin_mode = get_option( 'wp_autoplugin_plugin_mode', 'simple' );

        if ( 'complex' === $plugin_mode ) {
            return new \WP_Error( 'invalid_mode', 'Use generate_plugin_file() for complex mode plugins.' );
        }

        $prompt = <<<PROMPT
            Build a single-file WordPress plugin based on the specification below.
            
            ```
            $plan
            ```

            STRICT SECURITY REQUIREMENTS:
            - Use wp_verify_nonce() for all form/AJAX submissions.
            - Use current_user_can() for all restricted actions.
            - Sanitize ALL inputs using appropriate WordPress functions (sanitize_text_field, etc.).
            - Escape ALL outputs in HTML using esc_html(), esc_attr(), or wp_kses().
            - NEVER use \$_POST or \$_GET directly without sanitization.
            
            Do not use Markdown formatting. Ensure the response contains ONLY the complete, working code. Always use "WP-Autoplugin" for the Author, with Author URI: https://wp-autoplugin.com. Do not add the final closing "?>" tag.
            PROMPT;

        return $this->ai_api->send_prompt( $prompt );
    }

    /**
     * Generate a single file for a complex plugin.
     */
    public function generate_plugin_file( $file_info, $plan, $project_structure, $generated_files = [] ) {
        $file_type        = $file_info['type'];
        $file_path        = $file_info['path'];
        $file_description = $file_info['description'];

        $context = $this->build_file_context( $generated_files, $project_structure );

        if ( 'php' === $file_type ) {
            return $this->generate_php_file( $file_path, $file_description, $plan, $context );
        } elseif ( 'css' === $file_type ) {
            return $this->generate_css_file( $file_path, $file_description, $plan, $context );
        } elseif ( 'js' === $file_type ) {
            return $this->generate_js_file( $file_path, $file_description, $plan, $context );
        }

        return new \WP_Error( 'invalid_file_type', 'Unsupported file type: ' . $file_type );
    }

    /**
     * Generate a PHP file for the complex plugin.
     */
    private function generate_php_file( $file_path, $file_description, $plan, $context ) {
        $is_main_file = basename( $file_path ) === basename( $file_path, '.php' ) . '.php' && ! strpos( $file_path, '/' );

        $prompt = <<<PROMPT
            Generate a PHP file for a WordPress plugin:
            File Path: $file_path
            File Purpose: $file_description
            
            Plan: $plan
            $context

            Strict Requirements:
            - Follow WP coding standards (Tabs for indentation).
            - SECURITY: You MUST use Nonces, Capability Checks, and Sanitization/Escaping.
            - Database: Use \$wpdb->prepare() for all queries to prevent SQL injection.
            - Use "WP-Autoplugin" as author.
            - No closing "?>" tag.
            - Complete, functional code only.
            PROMPT;

        if ( $is_main_file ) {
            $prompt .= "- Include the full WordPress plugin header\n";
        } else {
            $prompt .= "- Do not include the plugin header\n";
        }

        $prompt .= "\nReturn ONLY the PHP code without markdown.";

        return $this->ai_api->send_prompt( $prompt );
    }

    /**
     * Generate a CSS file for the complex plugin.
     */
    private function generate_css_file( $file_path, $file_description, $plan, $context ) {
        $prompt = <<<PROMPT
            Generate a CSS file for: $file_path
            Purpose: $file_description
            Plan: $plan
            $context

            Requirements:
            - Unique CSS selectors to avoid conflicts.
            - Responsive design.
            Return ONLY CSS code without markdown.
            PROMPT;

        return $this->ai_api->send_prompt( $prompt );
    }

    /**
     * Generate a JavaScript file for the complex plugin.
     */
    private function generate_js_file( $file_path, $file_description, $plan, $context ) {
        $prompt = <<<PROMPT
            Generate a SECURE JavaScript file for: $file_path
            Purpose: $file_description
            Plan: $plan
            $context

            Security Requirements:
            - NEVER use innerHTML for user-generated content; use textContent or jQuery .text().
            - Include proper error handling for AJAX calls.
            - Use wp-localize-script data for AJAX URLs and Nonces.
            - Ensure compatibility with WordPress standards.
            
            Return ONLY the JavaScript code without markdown.
            PROMPT;

        return $this->ai_api->send_prompt( $prompt );
    }

    /**
     * Build context string.
     */
    private function build_file_context( $generated_files, $project_structure ) {
        $context = "Project Structure:\n";

        if ( isset( $project_structure['directories'] ) ) {
            $context .= 'Directories: ' . implode( ', ', $project_structure['directories'] ) . "\n";
        }

        if ( isset( $project_structure['files'] ) ) {
            $context .= "Files:\n";
            foreach ( $project_structure['files'] as $file ) {
                $context .= "- {$file['path']} ({$file['type']}): {$file['description']}\n";
            }
        }

        if ( ! empty( $generated_files ) ) {
            $context    .= "\nPreviously Generated Files:\n";
            $file_count  = count( $generated_files );
            $lines_limit = $file_count > 5 ? 1000 : 2000;

            foreach ( $generated_files as $file_path => $file_content ) {
                $context    .= "File: $file_path\n";
                $lines       = explode( "\n", $file_content );
                $lines_count = count( $lines );
                if ( $lines_count > $lines_limit ) {
                    $context .= "Content (truncated):\n```\n";
                    $context .= join( "\n", array_slice( explode( "\n", $file_content ), 0, $lines_limit ) );
                    $context .= "\n```\n";
                } else {
                    $context .= "Content:\n```\n$file_content\n```\n";
                }
            }
        }

        return $context;
    }

    /**
     * Review the complete generated codebase and suggest improvements.
     */
    public function review_generated_code( $plugin_plan, $project_structure, $generated_files ) {
        $context = $this->build_file_context( $generated_files, $project_structure );

        $prompt = <<<PROMPT
            Review this WordPress plugin codebase for CRITICAL errors and SECURITY VULNERABILITIES (XSS, SQLi, CSRF).
            
            Plan: $plugin_plan
            $context

            Analyze for:
            - Syntax errors.
            - Missing Nonce/Capability checks.
            - Unsafe JS (innerHTML usage).
            - Unsanitized input or unescaped output.
            
            Return a JSON object:
            {
                "review_summary": "Summary",
                "suggestions": [
                    {
                        "action": "UPDATE",
                        "file_path": "path",
                        "file_type": "type",
                        "reason": "Security/Functionality issue",
                        "description": "The exact code fix"
                    }
                ]
            }
            
            Return ONLY JSON. No markdown.
            PROMPT;

        return $this->ai_api->send_prompt( $prompt, '', [ 'response_format' => [ 'type' => 'json_object' ] ] );
    }
}
