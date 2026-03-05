<?php

if (!defined('ABSPATH')) exit;

class CR_Shortcode {

    public function __construct(){
        add_shortcode('calculadora_rescisao', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function assets(){
        wp_enqueue_style('cr-style', CR_URL.'assets/css/style.css', [], CR_VERSION);

        
        wp_enqueue_script(
            'cr-moment',
            'https:
            [],
            '2.29.4',
            true
        );

        
        wp_enqueue_script(
            'cr-script',
            CR_URL.'assets/js/app.js',
            ['jquery', 'cr-moment'],
            CR_VERSION,
            true
        );

        wp_localize_script('cr-script', 'cr_ajax', [
            'url' => admin_url('admin-ajax.php')
        ]);
    }

    public function render(){

        ob_start(); ?>

        <div id="cr-app">
            <form id="cr-form">

                <label>Data de início</label>
                <input type="text" name="data_inicio" id="data_inicio" placeholder="dd/mm/aaaa" required inputmode="numeric" autocomplete="off">

                <label>Data do último dia</label>
                <input type="text" name="data_fim" id="data_fim" placeholder="dd/mm/aaaa" required inputmode="numeric" autocomplete="off">

                <label>Salário</label>
                <input type="text" name="salario" id="salario" required inputmode="decimal" autocomplete="off" placeholder="Ex: 1.500,00">

                <label>Tipo de rescisão</label>
                <select name="tipo" id="tipo" required>
                    <option value="sem_justa_causa">Demissão sem justa causa</option>
                    <option value="pedido_demissao">Pedido de demissão</option>
                    <option value="justa_causa">Demissão por justa causa</option>
                    <option value="rescisao_indireta">Rescisão indireta</option>
                    <option value="termino_experiencia">Término do contrato de experiência</option>
                    <option value="antecipada_trabalhador">Rescisão antecipada por iniciativa do trabalhador</option>
                    <option value="antecipada_empregador">Rescisão antecipada por iniciativa do empregador</option>
                    <option value="mutuo_acordo">Rescisão por mútuo acordo</option>
                </select>

                <button type="button" id="cr-proximo">Próximo</button>

                <fieldset class="cr-verbas" id="cr-verbas" style="display:none;">
                    <legend>Selecione as verbas que deseja calcular</legend>

                    <label><input type="checkbox" name="verbas[]" value="saldo_salario" checked> Saldo de salário</label>
                    <label>
                      <input type="checkbox" name="verbas[]" value="aviso_previo" id="cr-aviso-checkbox">
                      Aviso prévio
                    </label>

                    <div id="cr-aviso-box" style="display:none; margin-left:20px;">
                      <label>Tipo de aviso</label>
                      <select name="aviso_tipo" id="cr-aviso-tipo">
                        <option value="trabalhado">Trabalhado</option>
                        <option value="indenizado">Indenizado</option>
                        <option value="nao_cumprido">Não cumprido (desconta do empregado)</option>
                      </select>
                    </div>
                    <label><input type="checkbox" name="verbas[]" value="ferias_vencidas"> Férias vencidas</label>
                    <div id="cr-periodos-ferias" class="cr-periodos-ferias" style="margin-left:20px; display:none;"></div>
                    <label><input type="checkbox" name="verbas[]" value="ferias_proporcionais"> Férias proporcionais</label>
                    <label><input type="checkbox" name="verbas[]" value="decimo_terceiro"> 13º salário proporcional</label>

                    <label>
                      <input type="checkbox" id="cr-fgts-master" name="verbas[]" value="fgts_master">
                      Calcular FGTS
                    </label>

                    <div id="cr-fgts-opcoes" style="margin-left:20px; display:none;">
                      <label><input type="checkbox" name="verbas[]" value="fgts_periodo"> Calcular FGTS do período</label>
                      <label><input type="checkbox" name="verbas[]" value="fgts_rescisorias"> Calcular FGTS sobre as verbas rescisórias</label>
                      <label><input type="checkbox" name="verbas[]" value="fgts_nao_depositado"> Calcular estimativa do FGTS não depositado</label>
                      <label><input type="checkbox" name="verbas[]" value="fgts_multa"> Calcular multa 40% sobre FGTS</label>
                    </div>

                    <label><input type="checkbox" name="verbas[]" value="inss"> Desconto INSS</label>
                    <label><input type="checkbox" name="verbas[]" value="irrf"> Desconto IRRF</label>
                </fieldset>

                <button type="submit" style="display:none;" id="cr-calcular">Calcular</button>
            </form>

            <div id="cr-resultado" role="region" aria-live="polite" aria-label="Resultado da simulação"></div>

            <section class="cr-seo" aria-label="Informações sobre cálculo de rescisão">
                <h2>Calculadora de Rescisão Trabalhista (CLT)</h2>
                <p>
                    Simule valores de rescisão como saldo de salário, férias, 13º e FGTS. Os resultados desta calculadora são
                    <strong>estimativas</strong> e podem variar conforme regras específicas do contrato e da legislação.
                </p>

                <h3>Como usamos as informações</h3>
                <p>
                    Você informa datas, salário e o motivo da rescisão. A calculadora aplica regras gerais e mostra um resumo com
                    proventos, descontos e FGTS (se selecionado).
                </p>

                <h3>Perguntas frequentes</h3>
                <div class="cr-faq">
                    <h4>O resultado é oficial?</h4>
                    <p>Não. É uma estimativa e não substitui cálculo trabalhista oficial.</p>

                    <h4>Como são contados avos de férias e 13º?</h4>
                    <p>Em regra, considera-se mês como completo quando houver 15 dias ou mais trabalhados no mês.</p>

                    <h4>FGTS sempre pode sacar?</h4>
                    <p>Depende do tipo de rescisão. Em geral, pedido de demissão e justa causa não dão direito a saque e multa.</p>
                </div>
            </section>

            <script type="application/ld+json">
            <?php
            echo wp_json_encode([
                '@context' => 'https:
                '@type' => 'FAQPage',
                'mainEntity' => [
                    [
                        '@type' => 'Question',
                        'name' => 'O resultado é oficial?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Não. É uma estimativa e não substitui cálculo trabalhista oficial.',
                        ],
                    ],
                    [
                        '@type' => 'Question',
                        'name' => 'Como são contados avos de férias e 13º?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Em regra, considera-se mês como completo quando houver 15 dias ou mais trabalhados no mês.',
                        ],
                    ],
                    [
                        '@type' => 'Question',
                        'name' => 'FGTS sempre pode sacar?',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => 'Depende do tipo de rescisão. Em geral, pedido de demissão e justa causa não dão direito a saque e multa.',
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ?>
            </script>

        </div>

        <script>
        jQuery(function($){
            $('#cr-proximo').on('click', function(){
                
                if(!$('#data_inicio').val() || !$('#data_fim').val() || !$('#salario').val() || !$('#tipo').val()){
                    alert('Preencha todas as informações iniciais.');
                    return;
                }
                
                $('#cr-verbas').slideDown();
                $('#cr-calcular').show();
                $(this).hide();
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }
}