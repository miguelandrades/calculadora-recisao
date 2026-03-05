<?php

if (!defined('ABSPATH')) exit;

class CR_Calculadora {

    private static function br_to_date($data){
        $data = is_string($data) ? trim($data) : '';
        if($data === '') return null;
        $d = DateTime::createFromFormat('d/m/Y', $data);
        return $d ? $d->format('Y-m-d') : null;
    }

    public static function calcular($dados){

    $salarioRaw = $dados['salario'] ?? 0;
    if (is_string($salarioRaw)) {
        $salarioRaw = trim($salarioRaw);
        $salarioRaw = str_replace('.', '', $salarioRaw);
        $salarioRaw = str_replace(',', '.', $salarioRaw);
    }
    $salario = floatval($salarioRaw);
    $inicio  = $dados['data_inicio'] ?? '';
    $fim     = $dados['data_fim'] ?? '';

    $inicio = self::br_to_date($inicio) ?: $inicio;
    $fim    = self::br_to_date($fim) ?: $fim;

    $tipo    = $dados['tipo'] ?? '';
    $avisoTipo = $dados['aviso_tipo'] ?? 'indenizado';
    $verbas = $dados['verbas'] ?? [];
    $verbas = is_array($verbas) ? $verbas : [$verbas];
    $verbas = array_values(array_filter(array_map('sanitize_text_field', $verbas)));

    $periodosFeriasSelecionados = $dados['periodos_ferias'] ?? [];
    $periodosFeriasSelecionados = is_array($periodosFeriasSelecionados) ? $periodosFeriasSelecionados : [$periodosFeriasSelecionados];
    $periodosFeriasSelecionados = array_values(array_filter(array_map('sanitize_text_field', $periodosFeriasSelecionados)));

    $periodos13Selecionados = $dados['periodos_13'] ?? [];
    $periodos13Selecionados = is_array($periodos13Selecionados) ? $periodos13Selecionados : [$periodos13Selecionados];
    $periodos13Selecionados = array_values(array_filter(array_map('sanitize_text_field', $periodos13Selecionados)));

    if (
        in_array('fgts', $verbas, true) &&
        !in_array('fgts_periodo', $verbas, true) &&
        !in_array($tipo, ['pedido_demissao', 'justa_causa'], true)
    ) {
        $verbas[] = 'fgts_periodo';
    }

    $permitidasPorTipo = [
        'sem_justa_causa'      => ['saldo_salario','aviso_previo','ferias_vencidas','ferias_proporcionais','decimo_terceiro','fgts_periodo','fgts_rescisorias','fgts_nao_depositado','fgts_multa','inss','irrf'],
        'rescisao_indireta'    => ['saldo_salario','aviso_previo','ferias_vencidas','ferias_proporcionais','decimo_terceiro','fgts_periodo','fgts_rescisorias','fgts_nao_depositado','fgts_multa','inss','irrf'],
        'pedido_demissao'      => ['saldo_salario','aviso_previo','ferias_vencidas','ferias_proporcionais','decimo_terceiro','inss','irrf'],
        'justa_causa'          => ['saldo_salario','ferias_vencidas','inss','irrf'],
        'mutuo_acordo'         => ['saldo_salario','aviso_previo','ferias_vencidas','ferias_proporcionais','decimo_terceiro','fgts_periodo','fgts_rescisorias','fgts_nao_depositado','fgts_multa','inss','irrf'],
        'termino_experiencia'  => ['saldo_salario','ferias_proporcionais','decimo_terceiro','fgts_periodo','inss','irrf'],
        'antecipada_trabalhador'=> ['saldo_salario','ferias_proporcionais','decimo_terceiro','fgts_periodo','inss','irrf'],
        'antecipada_empregador'=> ['saldo_salario','aviso_previo','ferias_proporcionais','decimo_terceiro','fgts_periodo','fgts_rescisorias','fgts_multa','inss','irrf'],
    ];

    $permitidas = $permitidasPorTipo[$tipo] ?? ['saldo_salario','inss','irrf'];
    $verbas = array_values(array_intersect($verbas, $permitidas));

    if (empty($verbas)) {
        $verbas = ['saldo_salario'];
    }

    if(!$inicio || !$fim){
        return CR_Response::erro('Datas inválidas');
    }

    $dataInicio = new DateTime($inicio);
    $dataFim    = new DateTime($fim);
    $intervalo  = $dataInicio->diff($dataFim);

    $anos  = $intervalo->y;
    $meses = $intervalo->m;
    $dias  = $intervalo->d;

    $temAviso = false;
    $temFeriasProp = false;
    $tem13 = false;
    $multaPct = 0;
    $descontoAvisoValor = 0;

    switch($tipo){
        case 'sem_justa_causa':
            $temAviso = true;
            $temFeriasProp = true;
            $tem13 = true;
            $multaPct = 0.40;
        break;

        case 'pedido_demissao':
            $temFeriasProp = true;
            $tem13 = true;
            $temAviso = true;
        break;

        case 'justa_causa':

        break;

        case 'mutuo_acordo':
            $temAviso = true;
            $temFeriasProp = true;
            $tem13 = true;
            $multaPct = 0.20;
        break;
    }

    $diasMes = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($fim)), date('Y', strtotime($fim)));
    $diasTrabalhados = date('d', strtotime($fim));
    $saldoSalario = ($salario / $diasMes) * $diasTrabalhados;

    $proventos = [];
    $total = 0;
    $descontosPrevios = [];

    $bases = [
        'inss' => 0,
        'irrf' => 0,
        'fgts' => 0
    ];

    if(in_array('saldo_salario',$verbas, true)){
        self::add_verba($proventos,$total,$bases,'Saldo de salário',$saldoSalario,'salario');
    }

    $diasAviso = 0;
    if($temAviso && in_array('aviso_previo',$verbas, true)){
        $diasAviso = 30 + ($anos * 3);
        if($diasAviso > 90) $diasAviso = 90;

        if($tipo === 'pedido_demissao'){
            if($avisoTipo === 'nao_cumprido'){
                $descontoAvisoValor = ($salario / 30) * $diasAviso;
            } else {
            }
        }
        else {
            if($avisoTipo === 'indenizado'){
                $valorAviso = ($salario / 30) * $diasAviso;

                if($tipo === 'mutuo_acordo'){
                    $valorAviso = $valorAviso / 2;
                    self::add_verba($proventos,$total,$bases,"Metade do aviso prévio indenizado ({$diasAviso} dias)",$valorAviso,'aviso_indenizado');
                } else {
                    self::add_verba($proventos,$total,$bases,"Aviso prévio indenizado ({$diasAviso} dias)",$valorAviso,'aviso_indenizado');
                }
            }
        }
    }

    if($descontoAvisoValor > 0){
        $descontosPrevios[] = [
            'titulo' => "Desconto aviso prévio ({$diasAviso} dias)",
            'valor' => round($descontoAvisoValor,2)
        ];
    }

    if($temAviso && $avisoTipo === 'indenizado' && $diasAviso > 0 && $tipo !== 'pedido_demissao'){
        $dataProjetada = clone $dataFim;
        $dataProjetada->modify("+{$diasAviso} days");
    } else {
        $dataProjetada = clone $dataFim;
    }

    $intervaloProj = $dataInicio->diff($dataProjetada);
    $anosP  = $intervaloProj->y;
    $mesesP = $intervaloProj->m;
    $diasP  = $intervaloProj->d;

    if(
        in_array('ferias_vencidas', $verbas, true) &&
        !empty($periodosFeriasSelecionados) &&
        ($tipo === 'sem_justa_causa' || $tipo === 'pedido_demissao' || $tipo === 'mutuo_acordo' || $tipo === 'justa_causa' || $tipo === 'rescisao_indireta')
    ){
        $periodosGeradosRaw = $dados['periodosGerados'] ?? null;
        $periodosGeradosMap = [];
        if (is_string($periodosGeradosRaw) && $periodosGeradosRaw !== '') {
            $decoded = json_decode($periodosGeradosRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $idx => $item) {
                    if (!is_array($item)) continue;
                    $ini = $item['inicio'] ?? null;
                    $fimG = $item['fim'] ?? null;
                    if (is_string($ini) && is_string($fimG) && $ini !== '' && $fimG !== '') {
                        $periodosGeradosMap[(string)$idx] = [$ini, $fimG];
                        
                        $periodosGeradosMap['periodo_' . (string)($idx + 1)] = [$ini, $fimG];
                        $periodosGeradosMap[(string)($idx + 1)] = [$ini, $fimG];
                    }
                }
            }
        }

        $admDiaMes = $dataInicio->format('m-d');

        $periodosNorm = [];
        foreach ($periodosFeriasSelecionados as $p) {
            $p = is_string($p) ? trim($p) : '';
            if ($p === '') continue;

            $ini = null;
            $fimAquisitivoStr = null;

            if (strpos($p, '|') !== false) {
                $partes = explode('|', $p);
                if (count($partes) === 2) {
                    $ini = trim($partes[0]);
                    $fimAquisitivoStr = trim($partes[1]);
                }
            }

            
            if (($ini === null || $fimAquisitivoStr === null) && preg_match('/^\d{4}-\d{4}$/', $p)) {
                [$y1, $y2] = explode('-', $p);
                $ini = $y1 . '-' . $admDiaMes;
                
                $fimTmp = new DateTime($y2 . '-' . $admDiaMes);
                $fimTmp->modify('-1 day');
                $fimAquisitivoStr = $fimTmp->format('Y-m-d');
            }

            
            if (($ini === null || $fimAquisitivoStr === null) && isset($periodosGeradosMap[$p])) {
                [$ini, $fimAquisitivoStr] = $periodosGeradosMap[$p];
            }

            if ($ini === null || $fimAquisitivoStr === null) continue;

            $inicioAquisitivo = DateTime::createFromFormat('Y-m-d', $ini);
            $fimAquisitivo    = DateTime::createFromFormat('Y-m-d', $fimAquisitivoStr);
            if(!$inicioAquisitivo || !$fimAquisitivo) continue;

            
            if($fimAquisitivo < $inicioAquisitivo) continue;

            $periodosNorm[] = [
                'ini' => $inicioAquisitivo,
                'fim' => $fimAquisitivo,
            ];
        }

        
        $uniq = [];
        $periodosNorm = array_values(array_filter($periodosNorm, function($p) use (&$uniq){
            $key = $p['ini']->format('Y-m-d') . '|' . $p['fim']->format('Y-m-d');
            if (isset($uniq[$key])) return false;
            $uniq[$key] = true;
            return true;
        }));

        usort($periodosNorm, function($a, $b){
            return $a['ini'] <=> $b['ini'];
        });

        foreach($periodosNorm as $p){
            $inicioAquisitivo = $p['ini'];
            $fimAquisitivo    = $p['fim'];

            
            $fimConcessivo = (clone $fimAquisitivo)->modify('+1 year');

            
            $emDobro = ($dataProjetada > $fimConcessivo);

            
            $iniBr = $inicioAquisitivo->format('d/m/Y');
            $fimBr = $fimAquisitivo->format('d/m/Y');

            if($emDobro){
                $valorFeriasVencidas = $salario * 2;
                $tituloFerias = "Férias de {$iniBr} a {$fimBr} (venc. em dobro)";
            } else {
                $valorFeriasVencidas = $salario;
                $tituloFerias = "Férias de {$iniBr} a {$fimBr} (venc.)";
            }

            
            $tercoVencidas = ($salario / 3) * ($emDobro ? 2 : 1);
            $tituloTerco = "1/3 constitucional ({$iniBr} a {$fimBr})";

            self::add_verba($proventos,$total,$bases,$tituloFerias,$valorFeriasVencidas,'indenizada');
            self::add_verba($proventos,$total,$bases,$tituloTerco,$tercoVencidas,'indenizada');
        }
    }

    if($temFeriasProp && in_array('ferias_proporcionais',$verbas, true)){
    
    $mesesTrabalhados = ($anosP * 12) + $mesesP;
    if($diasP >= 15){
        $mesesTrabalhados += 1;
    }

    $mesesFerias = $mesesTrabalhados % 12;

    
    if($mesesFerias <= 0){
        $mesesFerias = 0;
    }

    $valorFerias = ($salario / 12) * $mesesFerias;
    $terco = $valorFerias / 3;

    if($mesesFerias > 0){
        self::add_verba($proventos,$total,$bases,"Férias proporcionais ({$mesesFerias}/12)",$valorFerias,'indenizada');
        self::add_verba($proventos,$total,$bases,'1/3 constitucional de férias',$terco,'indenizada');
    }
    }

    if($tem13 && in_array('decimo_terceiro',$verbas, true)){
        
        
        $itens13 = self::calcular13_por_ano($dataInicio, $dataProjetada, $salario);

        
        if (!empty($periodos13Selecionados)) {

            
            $periodos13GeradosRaw = $dados['periodos13Gerados'] ?? null;
            $periodos13GeradosMap = [];
            if (is_string($periodos13GeradosRaw) && $periodos13GeradosRaw !== '') {
                $decoded13 = json_decode($periodos13GeradosRaw, true);
                if (is_array($decoded13)) {
                    foreach ($decoded13 as $idx => $item) {
                        if (!is_array($item)) continue;
                        $ini13 = $item['inicio'] ?? null;
                        $fim13 = $item['fim'] ?? null;
                        if (is_string($ini13) && is_string($fim13) && $ini13 !== '' && $fim13 !== '') {
                            $periodos13GeradosMap[(string)$idx] = [$ini13, $fim13];
                            $periodos13GeradosMap['periodo_' . (string)($idx + 1)] = [$ini13, $fim13];
                            $periodos13GeradosMap[(string)($idx + 1)] = [$ini13, $fim13];
                        }
                    }
                }
            }

            
            $admDiaMes = $dataInicio->format('m-d');

            $selecionadosChaves = [];
            foreach ($periodos13Selecionados as $p13) {
                $p13 = is_string($p13) ? trim($p13) : '';
                if ($p13 === '') continue;

                $ini13 = null;
                $fim13 = null;

                
                if (strpos($p13, '|') !== false) {
                    $partes = explode('|', $p13);
                    if (count($partes) === 2) {
                        $ini13 = trim($partes[0]);
                        $fim13 = trim($partes[1]);
                    }
                }

                
                if (($ini13 === null || $fim13 === null) && preg_match('/^\d{4}-\d{4}$/', $p13)) {
                    [$y1, $y2] = explode('-', $p13);
                    $ini13 = $y1 . '-' . $admDiaMes;
                    $fimTmp13 = new DateTime($y2 . '-' . $admDiaMes);
                    $fimTmp13->modify('-1 day');
                    $fim13 = $fimTmp13->format('Y-m-d');
                }

                
                if (($ini13 === null || $fim13 === null) && isset($periodos13GeradosMap[$p13])) {
                    [$ini13, $fim13] = $periodos13GeradosMap[$p13];
                }

                if ($ini13 && $fim13) {
                    $selecionadosChaves[$ini13 . '|' . $fim13] = true;
                }
            }

            
            if (!empty($selecionadosChaves)) {
                $itens13 = array_values(array_filter($itens13, function($item) use ($selecionadosChaves){
                    if (empty($item['inicio']) || empty($item['fim'])) return false;
                    $key = $item['inicio'] . '|' . $item['fim'];
                    return isset($selecionadosChaves[$key]);
                }));
            } else {
                
                $itens13 = [];
            }
        }

        foreach($itens13 as $item){
            self::add_verba(
                $proventos,
                $total,
                $bases,
                $item['titulo'],
                $item['valor'],
                'decimo_terceiro'
            );
        }
    }

    
    $fgts = [
        'depositado' => 0,
        'sobre_rescisorias' => 0,
        'nao_depositado' => 0,
        'multa' => 0,
        'saque_total' => 0
    ];

    $calcFgtsPeriodo     = in_array('fgts_periodo', $verbas, true);
    $calcFgtsRescisorias = in_array('fgts_rescisorias', $verbas, true);
    $calcFgtsNaoDep      = in_array('fgts_nao_depositado', $verbas, true);
    $calcFgtsMulta       = in_array('fgts_multa', $verbas, true);

    
    if($calcFgtsMulta && !$calcFgtsPeriodo && !$calcFgtsNaoDep && !$calcFgtsRescisorias){
        $calcFgtsPeriodo = true;
    }

    
    $temDireitoFGTS = ($tipo === 'sem_justa_causa' || $tipo === 'mutuo_acordo' || $tipo === 'rescisao_indireta');
    if ($tipo === 'justa_causa' || $tipo === 'pedido_demissao') {
        $temDireitoFGTS = false;
    }

    
    $fgtsDepositado = 0;
    if($calcFgtsPeriodo){
        $mesesTotais = ($anosP * 12) + $mesesP;
        
        if($diasP >= 15){
            $mesesTotais += 1;
        }
        $fgtsDepositado = ($salario * 0.08) * $mesesTotais;
    }

    
    $fgtsSobreRescisorias = 0;
    if($calcFgtsRescisorias){
        $fgtsSobreRescisorias = $bases['fgts'] * 0.08;
    }

    
    
    $fgtsNaoDepositado = 0;
    if($calcFgtsNaoDep){
        $mesesTotais = ($anosP * 12) + $mesesP;
        
        if($diasP >= 15){
            $mesesTotais += 1;
        }
        $fgtsNaoDepositado = ($salario * 0.08) * $mesesTotais;
    }

    
$saldoFGTS = round($fgtsDepositado + $fgtsSobreRescisorias + $fgtsNaoDepositado, 2);


$multaFgts = 0;
if($calcFgtsMulta && $temDireitoFGTS){
    $multaFgts = round($saldoFGTS * $multaPct, 2);
}

    
    $saqueTotal = 0;
    if($temDireitoFGTS){
    if($tipo === 'mutuo_acordo'){
        $saqueTotal = round(($saldoFGTS * 0.80) + $multaFgts, 2);
    } else {
        $saqueTotal = round($saldoFGTS + $multaFgts, 2);
    }
}

    
    if(!$temDireitoFGTS){
        $multaFgts = 0;
        $saqueTotal = 0;
    }

    
    $fgts = [
        'depositado' => round($fgtsDepositado, 2),
        'sobre_rescisorias' => round($fgtsSobreRescisorias, 2),
        'nao_depositado' => round($fgtsNaoDepositado, 2),
        'multa' => round($multaFgts, 2),
        'saque_total' => round($saqueTotal, 2)
    ];

    $descontos = $descontosPrevios ?? [];
    $totalDescontos = 0;
    foreach($descontos as $d){ $totalDescontos += $d['valor']; }

    
    $inss = 0;
    if(in_array('inss', $verbas, true) && $bases['inss'] > 0){
        $inss = self::calcularINSS($bases['inss']);
        $descontos[] = [
            'titulo' => 'Desconto INSS',
            'valor' => round($inss,2)
        ];
        $totalDescontos += $inss;
    }

    
    $baseIR = max($bases['irrf'] - $inss, 0);
    if(in_array('irrf', $verbas, true) && $baseIR > 0){
        $irrf = self::calcularIRRF($baseIR);
        if($irrf > 0){
            $descontos[] = [
                'titulo' => 'Desconto IRRF',
                'valor' => round($irrf,2)
            ];
            $totalDescontos += $irrf;
        }
    }

    
    $mensagens = [
        'Valores estimados — não substituem cálculo trabalhista oficial.'
    ];

    $itens = [];
    if(in_array('saldo_salario', $verbas, true)) $itens[] = 'saldo de salário';
    if(in_array('aviso_previo', $verbas, true)){
        if($tipo === 'pedido_demissao' && $avisoTipo === 'nao_cumprido'){
            $itens[] = 'desconto de aviso prévio';
        } elseif($avisoTipo === 'indenizado') {
            $itens[] = 'aviso prévio indenizado';
        } else {
            $itens[] = 'aviso prévio trabalhado';
        }
    }
    if(in_array('ferias_vencidas', $verbas, true)) $itens[] = 'férias vencidas';
    if(in_array('ferias_proporcionais', $verbas, true)) $itens[] = 'férias proporcionais';
    if(in_array('decimo_terceiro', $verbas, true)) $itens[] = '13º por ano';

    
    $fgtsMods = [];
    if(in_array('fgts_periodo', $verbas, true)) $fgtsMods[] = 'FGTS do período';
    if(in_array('fgts_rescisorias', $verbas, true)) $fgtsMods[] = 'FGTS sobre verbas rescisórias';
    if(in_array('fgts_nao_depositado', $verbas, true)) $fgtsMods[] = 'estimativa de FGTS não depositado';
    if(in_array('fgts_multa', $verbas, true)) $fgtsMods[] = 'multa do FGTS';

    if(!empty($itens)){
        $mensagens[] = 'Inclui: ' . implode(', ', $itens) . '.';
    }

    if(!empty($fgtsMods) && !in_array($tipo, ['pedido_demissao', 'justa_causa'], true)){
        $mensagens[] = 'FGTS: ' . implode(', ', $fgtsMods) . '.';
    }

    
    if($tipo === 'justa_causa'){
        $mensagens[] = 'Sem direito a saque/multa do FGTS em demissão por justa causa.';
    } elseif($tipo === 'pedido_demissao'){
        $mensagens[] = 'Em regra, pedido de demissão não dá direito a saque/multa do FGTS (exceto regras específicas como saque-aniversário).';
    } elseif($tipo === 'mutuo_acordo'){
        $mensagens[] = 'Mútuo acordo: multa do FGTS é 20% e saque é limitado a 80% do saldo.';
    }

    
    $totalLiquido = $total - $totalDescontos;
    if ($totalLiquido < 0) {
        $totalLiquido = 0;
    }

    return [
        'meta' => [
            'tipo_rescisao' => $tipo,
            'tempo_servico' => ['anos'=>$anosP,'meses'=>$mesesP,'dias'=>$diasP]
        ],
        'proventos' => $proventos,
        'descontos' => $descontos,
        'fgts' => $fgts,
        'resumo' => [
            'total_bruto' => round($total,2),
            'total_descontos' => round($totalDescontos,2),
            'total_liquido' => round($totalLiquido, 2),
        ],
        'mensagens' => $mensagens
    ];
}
    private static function calcular13_por_ano(DateTime $inicio, DateTime $fim, float $salario): array {
        $itens = [];

        $anoIni = (int)$inicio->format('Y');
        $anoFim = (int)$fim->format('Y');

        for($ano = $anoIni; $ano <= $anoFim; $ano++){
            $inicioAno = new DateTime("{$ano}-01-01");
            $fimAno    = new DateTime("{$ano}-12-31");

            
            $ini = ($inicio > $inicioAno) ? clone $inicio : $inicioAno;
            $end = ($fim < $fimAno) ? clone $fim : $fimAno;

            if($end < $ini) continue;

            $avos = self::contar_avos_mes($ini, $end);
            if($avos <= 0) continue;

            $valor = ($salario / 12) * $avos;

            $itens[] = [
                'titulo' => sprintf(
                    '13º de %s a %s (%d/12 avos)',
                    $ini->format('d/m/Y'),
                    $end->format('d/m/Y'),
                    $avos
                ),
                'valor' => round($valor, 2),
                
                'inicio' => $ini->format('Y-m-d'),
                'fim' => $end->format('Y-m-d'),
            ];
        }

        return $itens;
    }

    /**
     * Conta quantos meses (avos) têm >= 15 dias trabalhados dentro do intervalo.
     */
    private static function contar_avos_mes(DateTime $ini, DateTime $end): int {
        $count = 0;

        
        $cursor = new DateTime($ini->format('Y-m-01'));

        while($cursor <= $end){
            $mesIni = new DateTime($cursor->format('Y-m-01'));
            $mesFim = new DateTime($cursor->format('Y-m-t'));

            $a = ($ini > $mesIni) ? clone $ini : $mesIni;
            $b = ($end < $mesFim) ? clone $end : $mesFim;

            if($b >= $a){
                
                $dias = (int)$a->diff($b)->days + 1;
                if($dias >= 15) $count++;
            }

            $cursor->modify('+1 month');
        }

        return $count;
    }

    private static function add_verba(&$proventos,&$total,&$bases,$titulo,$valor,$tipo){
        $valor = round($valor,2);

        $proventos[] = [
            'titulo' => $titulo,
            'valor' => $valor
        ];

        $total += $valor;

        switch($tipo){
            case 'salario':
                $bases['inss'] += $valor;
                $bases['irrf'] += $valor;
                $bases['fgts'] += $valor;
            break;

            case 'decimo_terceiro':
                $bases['inss'] += $valor;
                $bases['irrf'] += $valor;
                
                $bases['fgts'] += $valor;
            break;

            case 'aviso_indenizado':
                $bases['fgts'] += $valor;
            break;

            case 'indenizada':
                
            break;
        }
    }

    private static function calcularINSS($base){
        $faixas = [
            [1412.00, 0.075],
            [2666.68, 0.09],
            [4000.03, 0.12],
            [7786.02, 0.14],
        ];
        $restante = $base;
        $anterior = 0;
        $inss = 0;
        foreach($faixas as $faixa){
            [$limite, $aliq] = $faixa;
            if($base > $anterior){
                $parcela = min($limite - $anterior, $restante);
                if($parcela > 0){
                    $inss += $parcela * $aliq;
                    $restante -= $parcela;
                }
            }
            $anterior = $limite;
            if($restante <= 0) break;
        }
        return max($inss,0);
    }

    private static function calcularIRRF($base){
        
        $faixas = [
            [2259.20, 0.0, 0.0],
            [2826.65, 0.075, 169.44],
            [3751.05, 0.15, 381.44],
            [4664.68, 0.225, 662.77],
            [PHP_FLOAT_MAX, 0.275, 896.00],
        ];
        foreach($faixas as $faixa){
            [$limite, $aliq, $deducao] = $faixa;
            if($base <= $limite){
                $ir = ($base * $aliq) - $deducao;
                return max($ir,0);
            }
        }
        return 0;
    }
}