<?php

if (!defined('ABSPATH')) exit;

class CR_Response {

    public static function modelo(){

        return [
            'meta' => [
                'tipo_rescisao' => null,
                'tempo_servico' => [
                    'anos' => 0,
                    'meses' => 0,
                    'dias' => 0
                ]
            ],

            'resumo' => [
                'total_bruto' => 0,
                'total_descontos' => 0,
                'total_liquido' => 0
            ],

            'proventos' => [],
            'descontos' => [],

            'fgts' => [
                'depositado' => 0,
                'multa' => 0,
                'saque_total' => 0
            ],

            'mensagens' => [
                'Valores estimados — não substituem cálculo trabalhista oficial.'
            ]
        ];
    }
}