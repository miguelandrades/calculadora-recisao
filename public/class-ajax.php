<?php

if (!defined('ABSPATH')) exit;

class CR_Ajax {

    public function __construct(){
        add_action('wp_ajax_cr_calcular', [$this, 'calcular']);
        add_action('wp_ajax_nopriv_cr_calcular', [$this, 'calcular']);
    }

    /**
     * Converte data BR (dd/mm/YYYY) para ISO (YYYY-mm-dd)
     */
    private static function br_to_us_date($data){
        $data = is_string($data) ? trim($data) : '';
        if($data === '') return false;

        $d = DateTime::createFromFormat('d/m/Y', $data);
        return $d ? $d->format('Y-m-d') : false;
    }

    public function calcular(){

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            wp_send_json(['erro' => 'Método inválido']);
        }

        // WP adiciona slashes em $_POST
        $post = wp_unslash($_POST);

        // Salário: aceita "1500", "1.500", "1,500" e "1500,00"
        $salario_raw = isset($post['salario']) ? (string) $post['salario'] : '0';
        $salario_raw = trim($salario_raw);
        // remove separador de milhar (.) e troca vírgula por ponto
        $salario_raw = str_replace('.', '', $salario_raw);
        $salario_raw = str_replace(',', '.', $salario_raw);
        $salario = floatval($salario_raw);

        $data_inicio = self::br_to_us_date($post['data_inicio'] ?? '');
        $data_fim    = self::br_to_us_date($post['data_fim'] ?? '');
        $tipo        = isset($post['tipo']) ? sanitize_text_field($post['tipo']) : '';

        // Arrays vindos do JS (normaliza para sempre virar array)
        $verbas = isset($post['verbas']) ? (array) $post['verbas'] : [];
        $periodos_ferias = isset($post['periodos_ferias']) ? (array) $post['periodos_ferias'] : [];
        $periodos_13 = isset($post['periodos_13']) ? (array) $post['periodos_13'] : [];

        // JSONs gerados no front (podem vir como string)
        $periodosGerados = isset($post['periodosGerados']) ? $post['periodosGerados'] : '';
        $periodos13Gerados = isset($post['periodos13Gerados']) ? $post['periodos13Gerados'] : '';

        // Sanitiza valores
        $verbas = array_values(array_filter(array_map('sanitize_text_field', $verbas)));
        $periodos_ferias = array_values(array_filter(array_map('sanitize_text_field', $periodos_ferias)));
        $periodos_13 = array_values(array_filter(array_map('sanitize_text_field', $periodos_13)));

        // Mantém JSON como string (sem quebrar), mas remove slashes e espaços extremos
        $periodosGerados = is_string($periodosGerados) ? trim((string)$periodosGerados) : '';
        $periodos13Gerados = is_string($periodos13Gerados) ? trim((string)$periodos13Gerados) : '';

        // (opcional) tipo de aviso, se existir no form
        $aviso_tipo = isset($post['aviso_tipo']) ? sanitize_text_field($post['aviso_tipo']) : '';

        if(!$data_inicio || !$data_fim){
            wp_send_json(['erro' => 'Data inválida']);
        }

        if(!class_exists('CR_Calculadora')){
            wp_send_json(['erro' => 'Classe não carregou']);
        }

        $dados = [
            'salario' => $salario,
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
            'tipo' => $tipo,
            'aviso_tipo' => $aviso_tipo,
            'verbas' => $verbas,
            'periodos_ferias' => $periodos_ferias,
            'periodos_13' => $periodos_13,
            'periodosGerados' => $periodosGerados,
            'periodos13Gerados' => $periodos13Gerados,
        ];

        $resultado = CR_Calculadora::calcular($dados);

        wp_send_json($resultado);
    }
}