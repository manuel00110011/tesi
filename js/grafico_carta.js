function creaGraficoSpeseCarta(canvasId, numeroCarta, dataInizio = null, dataFine = null) {
    const oggi = new Date();
    
    if (!dataInizio && !dataFine) {
        const primoGiornoMese = new Date(oggi.getFullYear(), oggi.getMonth(), 1);
        const ultimoGiornoMese = new Date(oggi.getFullYear(), oggi.getMonth() + 1, 0);
        
        dataInizio = primoGiornoMese.toISOString().split('T')[0];
        dataFine = ultimoGiornoMese.toISOString().split('T')[0];
    }
    
    const url = `api/dati_grafico_carta.php?numero_carta=${numeroCarta}&data_inizio=${dataInizio}&data_fine=${dataFine}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.errore) {
                console.error('Errore:', data.errore);
                return;
            }

            const labels = data.map(item => {
                const data = new Date(item.data);
                return data.toLocaleDateString('it-IT', { 
                    day: '2-digit', 
                    month: '2-digit',
                    year: 'numeric'
                });
            });

            const importi = data.map(item => parseFloat(item.importo));

            if (window.graficoCartaInstance) {
                window.graficoCartaInstance.destroy();
            }

            const ctx = document.getElementById(canvasId).getContext('2d');
            window.graficoCartaInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Spese (€)',
                        data: importi,
                        borderColor: '#000000',
                        backgroundColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#000000',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '€ ' + value.toFixed(2);
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Andamento Spese Carta',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: 20
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const index = context[0].dataIndex;
                                    return 'Data: ' + labels[index];
                                },
                                label: function(context) {
                                    const index = context.dataIndex;
                                    return 'Importo: € ' + importi[index].toFixed(2);
                                }
                            },
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#000000',
                            borderWidth: 1
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });

            aggiornaStatisticheCarta(data);
        })
        .catch(error => {
            console.error('Errore nel caricamento dei dati:', error);
        });
}

function aggiornaStatisticheCarta(dati) {
    const totaleSpese = dati.reduce((sum, item) => sum + parseFloat(item.importo), 0);
    const numeroTransazioni = dati.length;
    const spesaMedia = numeroTransazioni > 0 ? totaleSpese / numeroTransazioni : 0;
    const spesaMassima = numeroTransazioni > 0 ? Math.max(...dati.map(item => parseFloat(item.importo))) : 0;

    const statsContainer = document.getElementById('statistiche-carta');
    if (statsContainer) {
        statsContainer.innerHTML = `
            <div class="bg-white border rounded p-3 mb-3">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h5 class="text-dark mb-1">€ ${totaleSpese.toFixed(2)}</h5>
                        <small class="text-muted">Totale Spese</small>
                    </div>
                    <div class="col-md-3">
                        <h5 class="text-dark mb-1">${numeroTransazioni}</h5>
                        <small class="text-muted">Transazioni</small>
                    </div>
                    <div class="col-md-3">
                        <h5 class="text-dark mb-1">€ ${spesaMedia.toFixed(2)}</h5>
                        <small class="text-muted">Spesa Media</small>
                    </div>
                    <div class="col-md-3">
                        <h5 class="text-dark mb-1">€ ${spesaMassima.toFixed(2)}</h5>
                        <small class="text-muted">Spesa Massima</small>
                    </div>
                </div>
            </div>
        `;
    }
}

function aggiornaGraficoConFiltri() {
    const cartaSelezionata = document.querySelector('select[name="carta_selezionata"]').value;
    const dataDa = document.querySelector('input[name="data_da"]').value;
    const dataA = document.querySelector('input[name="data_a"]').value;
    const filtro = document.querySelector('select[name="filtro"]').value;

    if (cartaSelezionata && cartaSelezionata !== 'tutte') {
        let dataInizio = null;
        let dataFine = null;

        const oggi = new Date();

        if (dataDa && dataA) {
            dataInizio = dataDa;
            dataFine = dataA;
        } else if (dataDa) {
            dataInizio = dataDa;
        } else if (dataA) {
            dataFine = dataA;
        } else if (filtro !== 'tutti') {
            switch (filtro) {
                case 'mese':
                    const unMeseFa = new Date(oggi);
                    unMeseFa.setMonth(oggi.getMonth() - 1);
                    dataInizio = unMeseFa.toISOString().split('T')[0];
                    dataFine = oggi.toISOString().split('T')[0];
                    break;
                case '3mesi':
                    const treeMesiFa = new Date(oggi);
                    treeMesiFa.setMonth(oggi.getMonth() - 3);
                    dataInizio = treeMesiFa.toISOString().split('T')[0];
                    dataFine = oggi.toISOString().split('T')[0];
                    break;
                case 'anno':
                    const unAnnoFa = new Date(oggi);
                    unAnnoFa.setFullYear(oggi.getFullYear() - 1);
                    dataInizio = unAnnoFa.toISOString().split('T')[0];
                    dataFine = oggi.toISOString().split('T')[0];
                    break;
            }
        } else {
            // deafult è l'ultimo mese dalla data attuale
            const unMeseFa = new Date(oggi);
            unMeseFa.setMonth(oggi.getMonth() - 1);
            dataInizio = unMeseFa.toISOString().split('T')[0];
            dataFine = oggi.toISOString().split('T')[0];
        }

        creaGraficoSpeseCarta('graficoCartaCanvas', cartaSelezionata, dataInizio, dataFine);
    }
}