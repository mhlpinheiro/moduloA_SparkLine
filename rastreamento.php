<?php
require_once __DIR__ . "/auth.php";
verificar_autenticacao();
require_once __DIR__ . "/conexao.php";

$entregas = $pdo->query("SELECT * FROM entregas WHERE status != 'pendente'")->fetchAll();

require_once __DIR__ . "/header.php";
?>
<div class="container">
    <h2>Rastreamento de Entregas em Tempo Real - RF06</h2>
    <div class="card" style="margin-bottom: 1.5rem;">
        <div id="map" style="height: 450px; width: 100%; border-radius: 8px;"></div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var map = L.map('map').setView([-23.0264, -45.5558], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var pontos = [
            { lat: -23.0264, lng: -45.5558, titulo: "PED-1001: Supermercado Central", status: "Em Rota", veiculo: "ABC-1234" },
            { lat: -23.0301, lng: -45.5480, titulo: "PED-1002: Loja de Eletro", status: "Em Rota", veiculo: "XYZ-9876" },
            { lat: -23.0210, lng: -45.5510, titulo: "PED-1003: Farmácia Saúde", status: "Entregue", veiculo: "ABC-1234" }
        ];

        pontos.forEach(function(p) {
            var marker = L.marker([p.lat, p.lng]).addTo(map);
            marker.bindPopup("<b>" + p.titulo + "</b><br>Status: " + p.status + "<br>Veículo: " + p.veiculo);
        });
    });
    </script>
</div>
<?php require_once __DIR__ . "/footer.php"; ?>