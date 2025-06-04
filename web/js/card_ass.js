// Main function yang dijalankan saat dokumen siap
$(document).ready(function () {
    let progressChartInstance = null;
    let progressTypeChartInstance = null;

    fetchData();

    $("#filterForm").on("submit", function (e) {
        e.preventDefault();
        fetchData();
    });

    function fetchData() {
        let order_by = $("#order_by").val();
        let transaksi = $("#transaksi").val();
        let start_date = $("#start_date").val();
        let end_date = $("#end_date").val();
        let progress_order = $("#progress_order").val();

        $.ajax({
            url: "dashboard_ass.php",
            type: "GET",
            dataType: "json",
            data: {
                ajax: "true",
                order_by: order_by,
                transaksi: transaksi,
                start_date: start_date,
                end_date: end_date,
                progress_order: progress_order
            },
            success: function (response) {
                console.log("Response:", response);

                // Update statistik
                $("#sisa_order_count").text(response.sisa_order || 0);
                $("#sisa_pickup_count").text(response.sisa_pickup || 0);
                $("#order_count").text(response.orders_count?.Order || 0);
                $("#close_count").text(response.orders_count?.Close || 0);

                // Update tabel produktivitas
                updateProduktifitiTable(response.produktifitiData);
                updateCharts(response);
            },
            error: function (xhr, status, error) {
                console.error("Error:", error);
                $("#table-body").html("<tr><td colspan='12'>Gagal mengambil data</td></tr>");
                console.log("Response Text:", xhr.responseText);
            }
        });
    }

    function updateProduktifitiTable(data) {
        let tableBody = $("#table-body");
        tableBody.empty();
        
        if (!data || data.length === 0) {
            tableBody.html("<tr><td colspan='12'>Tidak ada data</td></tr>");
            return;
        }
    
        let order_by = $("#order_by").val();
        let transaksi = $("#transaksi").val();
        let start_date = $("#start_date").val();
        let end_date = $("#end_date").val();
        let progress_order = $("#progress_order").val();
    
        $.each(data, function (index, item) {
            let logLink = `log_ass.php?nama=${encodeURIComponent(item.Nama)}`;
            
            if (order_by) logLink += `&order_by=${encodeURIComponent(order_by)}`;
            if (transaksi) logLink += `&transaksi=${encodeURIComponent(transaksi)}`;
            if (start_date) logLink += `&start_date=${encodeURIComponent(start_date)}`;
            if (end_date) logLink += `&end_date=${encodeURIComponent(end_date)}`;
            if (progress_order) logLink += `&progress_order=${encodeURIComponent(progress_order)}`;
            
            let row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.Nama || '-'}</td>
                    <td>${item.SENDMYI || 0}</td>
                    <td>${item.CEKPASSWORDWIFI || 0}</td>
                    <td>${item.CEKREDAMAN || 0}</td>
                    <td>${item.INTERNETERROR || 0}</td>
                    <td>${item.GANTIONT || 0}</td>
                    <td>${item.GANTISTB || 0}</td>
                    <td>${item.OMSET || 0}</td>
                    <td>${item.VOIPERROR || 0}</td>
                    <td>${item.USERERROR || 0}</td>
                    <td>${item.RecordCount || 0}</td>
                    <td><a href="${logLink}">Lihat Log</a></td>
                </tr>
            `;
            tableBody.append(row);
        });
    }
    
    function updateCharts(data) {
        updateProgressChart(data.progressChart);
        updateProgressTypeChart(data.progressTypeChart);
    }

    function updateProgressChart(data) {
        const ctx = document.getElementById('progressChart')?.getContext('2d');
        if (!ctx) return;

        if (progressChartInstance) progressChartInstance.destroy();

        progressChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => d.tanggal),
                datasets: [{
                    label: 'Total Orders per Date',
                    data: data.map(d => d.total),
                    borderColor: '#34495e',
                    borderWidth: 1
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Total Orders per Date',
                        padding: { bottom: 20 },
                        font: { size: 17, color: 'black' }
                    }
                },
                responsive: true
            }
        });
    }

    function updateProgressTypeChart(data) {
        const ctx = document.getElementById('progressTypeChart')?.getContext('2d');
        if (!ctx) return;

        if (progressTypeChartInstance) progressTypeChartInstance.destroy();

        // Ubah menjadi bar chart
        progressTypeChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.progress_order),
                datasets: [{
                    label: 'Order Progress Status',
                    data: data.map(d => d.total),
                    backgroundColor: "#34495e",
                    borderWidth: 1
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Type of Progress',
                        padding: { bottom: 20 },
                        font: { size: 17, color: 'black' }
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});