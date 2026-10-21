<?php
// Biblioteca de roteirização (RF05).
// Abordagem: 1) agrupa pedidos por veículo respeitando capacidade (kg e m³);
// 2) ordena as paradas com heurística do vizinho mais próximo, usando
//    distância de Haversine, janela de entrega, prioridade e horário simulado.

const DEPOSITO_LAT = -23.0264;
const DEPOSITO_LON = -45.5558;
const FATOR_VIARIO = 1.3;          // converte linha reta em distância aproximada de ruas
const VELOCIDADE_MEDIA_KMH = 30.0;
const TEMPO_PARADA_MIN = 10;
const PRECO_COMBUSTIVEL = 6.20;    // R$ por litro

function pesoPrioridade(string $p): int
{
    return match ($p) {
        "urgente" => 4,
        "alta" => 3,
        "media" => 2,
        default => 1
    };
}

function distanciaKm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

    return 2 * $r * asin(min(1.0, sqrt($a)));
}

function horaParaMinutos(?string $valor): ?int
{
    if ($valor === null || $valor === "") {
        return null;
    }
    if (strpos($valor, " ") !== false) {
        $valor = substr($valor, strrpos($valor, " ") + 1);
    }
    $partes = explode(":", $valor);
    if (count($partes) < 2) {
        return null;
    }
    return ((int) $partes[0]) * 60 + (int) $partes[1];
}

function minutosParaHora(int $min): string
{
    $min = max(0, $min);
    return sprintf("%02d:%02d", intdiv($min, 60) % 24, $min % 60);
}

// Converte endereço em coordenadas (OpenStreetMap Nominatim).
// Se falhar (sem internet), devolve null e o sistema usa distância estimada (RNF06).
function geocodificar(string $endereco): ?array
{
    if (!function_exists("curl_init")) {
        return null;
    }

    $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=br&q="
        . urlencode($endereco);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => "SENN-AI-X/1.0 (projeto escolar SENAI)"
    ]);
    $resposta = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resposta === false || $http !== 200) {
        return null;
    }

    $dados = json_decode($resposta, true);
    if (!is_array($dados) || empty($dados[0]["lat"]) || empty($dados[0]["lon"])) {
        return null;
    }

    return [(float) $dados[0]["lat"], (float) $dados[0]["lon"]];
}

// Ordena as paradas de UM veículo, simulando o relógio da rota.
function sequenciarParadas(array $entregas, int $inicioMin): array
{
    $lat = DEPOSITO_LAT;
    $lon = DEPOSITO_LON;
    $relogio = $inicioMin;
    $restantes = array_values($entregas);
    $paradas = [];
    $kmTotal = 0.0;

    while ($restantes) {
        $melhorIdx = null;
        $melhorScore = INF;
        $melhor = null;

        foreach ($restantes as $i => $e) {
            $temCoord = $e["latitude"] !== null && $e["longitude"] !== null;

            $km = $temCoord
                ? distanciaKm($lat, $lon, (float) $e["latitude"], (float) $e["longitude"]) * FATOR_VIARIO
                : 5.0;

            $chegada = $relogio + (int) round($km / VELOCIDADE_MEDIA_KMH * 60);
            $ini = horaParaMinutos($e["janela_inicio"]);
            $fim = horaParaMinutos($e["janela_fim"]);

            $servico = $ini !== null ? max($chegada, $ini) : $chegada;
            $espera = $servico - $chegada;

            $score = $km + $espera * 0.2 - pesoPrioridade((string) $e["prioridade"]) * 1.5;

            if ($fim !== null) {
                if ($servico > $fim) {
                    $score += 1000;                      // estouraria a janela
                } else {
                    $score += ($fim - $servico) / 60;    // janelas apertadas primeiro
                }
            }

            if ($score < $melhorScore) {
                $melhorScore = $score;
                $melhorIdx = $i;
                $melhor = [
                    "entrega" => $e,
                    "eta" => $servico,
                    "km_trecho" => $km,
                    "atrasada" => $fim !== null && $servico > $fim,
                    "tem_coord" => $temCoord
                ];
            }
        }

        $kmTotal += $melhor["km_trecho"];
        $relogio = $melhor["eta"] + TEMPO_PARADA_MIN;

        if ($melhor["tem_coord"]) {
            $lat = (float) $melhor["entrega"]["latitude"];
            $lon = (float) $melhor["entrega"]["longitude"];
        }

        $paradas[] = $melhor;
        unset($restantes[$melhorIdx]);
    }

    // Retorno ao depósito entra na última parada (custo e km reais da rota).
    $volta = distanciaKm($lat, $lon, DEPOSITO_LAT, DEPOSITO_LON) * FATOR_VIARIO;
    if ($paradas) {
        $paradas[count($paradas) - 1]["km_trecho"] += $volta;
    }
    $kmTotal += $volta;

    return ["paradas" => $paradas, "km_total" => $kmTotal];
}

// Gera e grava as rotas de todas as entregas pendentes.
function gerarRotas(PDO $pdo, int $inicioMin): array
{
    set_time_limit(90);

    $pendentes = $pdo->query(
        "SELECT id, codigo_pedido, cliente_nome, endereco, latitude, longitude,
                janela_inicio, janela_fim, peso_kg, volume_m3, prioridade
         FROM entregas
         WHERE status = 'pendente'"
    )->fetchAll();

    if (!$pendentes) {
        return ["tipo" => "aviso", "mensagem" => "Não há entregas pendentes para otimizar.", "avisos" => []];
    }

    $veiculos = $pdo->query(
        "SELECT id, placa, modelo, capacidade_kg, capacidade_m3, autonomia_km_l
         FROM veiculos
         WHERE status = 'disponivel'
         ORDER BY capacidade_kg ASC"
    )->fetchAll();

    // Só motoristas disponíveis e com CNH válida.
    $motoristas = $pdo->query(
        "SELECT id, nome, veiculo_id
         FROM motoristas
         WHERE status = 'disponivel' AND validade_cnh >= CURDATE()
         ORDER BY nome ASC"
    )->fetchAll();

    if (!$veiculos || !$motoristas) {
        return [
            "tipo" => "erro",
            "mensagem" => "É preciso ter ao menos um veículo disponível e um motorista disponível com CNH válida.",
            "avisos" => []
        ];
    }

    $avisos = [];

    // 1) Geocodificação (limite de 8 consultas por execução, 1 por segundo).
    $gravaCoord = $pdo->prepare("UPDATE entregas SET latitude = ?, longitude = ? WHERE id = ?");
    $consultas = 0;

    foreach ($pendentes as $i => $e) {
        if ($e["latitude"] !== null && $e["longitude"] !== null) {
            continue;
        }
        if ($consultas >= 8) {
            continue;
        }
        if ($consultas > 0) {
            sleep(1);
        }
        $consultas++;

        $coord = geocodificar((string) $e["endereco"]);
        if ($coord) {
            $gravaCoord->execute([$coord[0], $coord[1], $e["id"]]);
            $pendentes[$i]["latitude"] = $coord[0];
            $pendentes[$i]["longitude"] = $coord[1];
        }
    }

    foreach ($pendentes as $e) {
        if ($e["latitude"] === null || $e["longitude"] === null) {
            $avisos[] = "Pedido {$e['codigo_pedido']} sem coordenadas: foi usada distância estimada e ele não aparece no mapa.";
        }
    }

    // 2) Agrupamento por capacidade: prioridade e fim da janela primeiro.
    usort($pendentes, function ($a, $b) {
        $c = pesoPrioridade((string) $b["prioridade"]) <=> pesoPrioridade((string) $a["prioridade"]);
        return $c !== 0 ? $c : strcmp((string) $a["janela_fim"], (string) $b["janela_fim"]);
    });

    $rotas = [];
    $veiculosUsados = [];
    $motoristasUsados = [];

    foreach ($pendentes as $e) {
        $peso = (float) $e["peso_kg"];
        $volume = (float) $e["volume_m3"];
        $alocada = false;

        foreach ($rotas as $k => $r) {
            if (
                $r["peso"] + $peso <= (float) $r["veiculo"]["capacidade_kg"] &&
                $r["volume"] + $volume <= (float) $r["veiculo"]["capacidade_m3"]
            ) {
                $rotas[$k]["entregas"][] = $e;
                $rotas[$k]["peso"] += $peso;
                $rotas[$k]["volume"] += $volume;
                $alocada = true;
                break;
            }
        }

        if ($alocada) {
            continue;
        }

        $veiculo = null;
        foreach ($veiculos as $v) {
            if (isset($veiculosUsados[$v["id"]])) {
                continue;
            }
            if ($peso <= (float) $v["capacidade_kg"] && $volume <= (float) $v["capacidade_m3"]) {
                $veiculo = $v;
                break;
            }
        }

        if (!$veiculo) {
            $avisos[] = "Pedido {$e['codigo_pedido']} não coube em nenhum veículo disponível e continua pendente.";
            continue;
        }

        // Prefere o motorista vinculado ao veículo; depois um sem vínculo.
        $motorista = null;
        foreach ([true, false] as $somenteVinculado) {
            foreach ($motoristas as $m) {
                if (isset($motoristasUsados[$m["id"]])) {
                    continue;
                }
                if ($somenteVinculado && (int) $m["veiculo_id"] === (int) $veiculo["id"]) {
                    $motorista = $m;
                    break 2;
                }
                if (!$somenteVinculado && $m["veiculo_id"] === null) {
                    $motorista = $m;
                    break 2;
                }
            }
        }

        if (!$motorista) {
            $avisos[] = "Pedido {$e['codigo_pedido']} sem motorista compatível disponível e continua pendente.";
            continue;
        }

        $veiculosUsados[$veiculo["id"]] = true;
        $motoristasUsados[$motorista["id"]] = true;

        $rotas[] = [
            "veiculo" => $veiculo,
            "motorista" => $motorista,
            "entregas" => [$e],
            "peso" => $peso,
            "volume" => $volume
        ];
    }

    if (!$rotas) {
        return ["tipo" => "erro", "mensagem" => "Nenhuma rota pôde ser montada.", "avisos" => $avisos];
    }

    // 3) Sequenciamento e gravação em transação.
    try {
        $pdo->beginTransaction();

        $upEntrega = $pdo->prepare(
            "UPDATE entregas
             SET motorista_id = ?, veiculo_id = ?, status = 'em_rota',
                 ordem_parada = ?, distancia_km = ?, custo_estimado = ?, eta_prevista = ?
             WHERE id = ? AND status = 'pendente'"
        );
        $upVeiculo = $pdo->prepare("UPDATE veiculos SET status = 'em_rota' WHERE id = ?");
        $upMotorista = $pdo->prepare("UPDATE motoristas SET status = 'em_rota' WHERE id = ?");

        $totalEntregas = 0;
        $kmGeral = 0.0;

        foreach ($rotas as $r) {
            $seq = sequenciarParadas($r["entregas"], $inicioMin);
            $autonomia = max(1.0, (float) $r["veiculo"]["autonomia_km_l"]);
            $ordem = 1;

            foreach ($seq["paradas"] as $p) {
                $custo = $p["km_trecho"] / $autonomia * PRECO_COMBUSTIVEL;

                $upEntrega->execute([
                    $r["motorista"]["id"],
                    $r["veiculo"]["id"],
                    $ordem,
                    round($p["km_trecho"], 2),
                    round($custo, 2),
                    minutosParaHora($p["eta"]) . ":00",
                    $p["entrega"]["id"]
                ]);

                if ($p["atrasada"]) {
                    $avisos[] = "Pedido {$p['entrega']['codigo_pedido']} deve chegar após o fim da janela (previsto "
                        . minutosParaHora($p["eta"]) . ").";
                }

                $ordem++;
                $totalEntregas++;
            }

            $upVeiculo->execute([$r["veiculo"]["id"]]);
            $upMotorista->execute([$r["motorista"]["id"]]);
            $kmGeral += $seq["km_total"];
        }

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao gravar rotas: " . $erro->getMessage());
        return ["tipo" => "erro", "mensagem" => "Não foi possível gravar as rotas. Tente novamente.", "avisos" => $avisos];
    }

    return [
        "tipo" => "sucesso",
        "mensagem" => sprintf(
            "%d rota(s) gerada(s) com %d entrega(s) e cerca de %s km no total. Rotas liberadas para os motoristas.",
            count($rotas),
            $totalEntregas,
            number_format($kmGeral, 1, ",", ".")
        ),
        "avisos" => $avisos
    ];
}