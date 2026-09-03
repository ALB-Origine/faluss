<?php

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'Elementor\\Widget_Base' ) ) {
    exit;
}

final class Faluss_Identity_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'faluss_identity_passwordless';
    }

    public function get_title() {
        return __( 'Connexion Faluss', 'faluss-identity' );
    }

    public function get_icon() {
        return 'eicon-lock-user';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_style_depends() {
        return array( Faluss_Identity_Passwordless::STYLE_HANDLE );
    }

    protected function register_controls() {
        $this->start_controls_section( 'content_section', array( 'label' => __( 'Contenu', 'faluss-identity' ) ) );
        $this->add_control( 'heading', array( 'label' => __( 'Titre', 'faluss-identity' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __( 'Bienvenue sur Faluss', 'faluss-identity' ) ) );
        $this->add_control( 'intro', array( 'label' => __( 'Introduction', 'faluss-identity' ), 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => __( 'Entrez votre adresse e-mail pour recevoir un code de connexion.', 'faluss-identity' ) ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style_section', array( 'label' => __( 'Style Faluss', 'faluss-identity' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
        $this->add_control( 'accent_color', array( 'label' => __( 'Couleur d’accent', 'faluss-identity' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#9b5cff' ) );
        $this->add_control( 'surface_color', array( 'label' => __( 'Fond', 'faluss-identity' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#171225' ) );
        $this->add_control( 'text_color', array( 'label' => __( 'Texte', 'faluss-identity' ), 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#f7f3ff' ) );
        $this->add_control( 'radius', array( 'label' => __( 'Arrondi', 'faluss-identity' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'default' => array( 'size' => 18, 'unit' => 'px' ) ) );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $settings['radius'] = isset( $settings['radius']['size'] ) ? $settings['radius']['size'] : 18;
        echo Faluss_Identity_Passwordless::render_form( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe, server-rendered widget markup.
    }
}
