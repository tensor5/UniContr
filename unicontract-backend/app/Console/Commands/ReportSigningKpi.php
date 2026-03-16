<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportSigningKpi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unicontr:report-signing-kpi
                            {--years=5 : Rolling window when from-aa/to-aa are not provided}
                            {--from-aa= : Start AA year (YYYY)}
                            {--to-aa= : End AA year (YYYY)}
                            {--providers=FIRMAIO,USIGN,FIRMA_GRAFOMETRICA,PRESA_VISIONE : Comma-separated tipo_accettazione values}
                            {--export= : Relative path under storage/app for CSV export}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build signing reports and KPI for precontr/insegnamento/validazioni';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        [$fromYear, $toYear] = $this->resolveYearWindow();
        $providers = $this->resolveProviders();

        $this->info('Report KPI firma contratti');
        $this->line(sprintf('Finestra AA: %d - %d', $fromYear, $toYear));
        $this->line(sprintf('Provider firma: %s', implode(', ', $providers)));

        $factSql = $this->buildFactSql($providers);
        $bindings = $this->buildFactBindings($fromYear, $toYear);

        $totalSigned = $this->queryTotalSigned($factSql, $bindings);
        $totalContracts = $this->queryTotalContracts($factSql, $bindings);
        $sinceFirstFirmaio = $this->querySignedSinceFirstFirmaio($factSql);
        $yearlyTrend = $this->queryYearlyTrend($factSql, $bindings);
        $durationByYear = $this->queryDurationByYear($factSql, $bindings);
        $globalKpi = $this->queryGlobalKpi($factSql, $bindings);
        $dataQuality = $this->queryDataQuality($providers);
        $stepTimesTotal = $this->queryStepTimesTotal($factSql, $bindings);
        $stepTimesByYear = $this->queryStepTimes($factSql, $bindings);

        $this->newLine();
        $this->info('1) Totali contratti (ultimi anni in finestra)');
        $this->table(
            ['totale_contratti', 'contratti_firmati'],
            [[(int)$totalContracts, (int)$totalSigned]]
        );

        $this->newLine();
        $this->info('1b) Contratti firmati dal primo FIRMAIO a oggi');
        $firstFirmaioDateFormatted = $sinceFirstFirmaio->first_firmaio_sign_date !== null
            ? Carbon::parse($sinceFirstFirmaio->first_firmaio_sign_date)->format('d/m/Y')
            : null;
        $firmaioOnSignedPct = (int)$sinceFirstFirmaio->signed_total_since_first_firmaio > 0
            ? round((((int)$sinceFirstFirmaio->signed_firmaio_since_first_firmaio / (int)$sinceFirstFirmaio->signed_total_since_first_firmaio) * 100), 2)
            : 0.0;
        $this->table(
            ['prima_data_firmaio', 'contratti_firmati', 'contratti_firmati_firmaio', 'pct_firmaio_su_firmati'],
            [[
                $firstFirmaioDateFormatted,
                (int)$sinceFirstFirmaio->signed_total_since_first_firmaio,
                (int)$sinceFirstFirmaio->signed_firmaio_since_first_firmaio,
                $firmaioOnSignedPct,
            ]]
        );

        $this->newLine();
        $this->info('2) Totale contratti firmati per anno');
        $this->table(
            ['anno_aa', 'contratti_firmati', 'contratti_aperti', 'tot_firmaio', 'tot_usign', 'tot_firma_grafometrica'],
            array_map(
                fn ($r) => [
                    (int)$r->aa_year,
                    (int)$r->signed_count,
                    (int)$r->open_count,
                    (int)$r->tot_firmaio,
                    (int)$r->tot_usign,
                    (int)$r->tot_firma_grafometrica,
                ],
                $yearlyTrend
            )
        );

        $this->newLine();
        $this->info('3) Tempo firma (giorni) per anno');
        $this->table(
            ['anno_aa', 'record_firmati', 'media_giorni', 'min_giorni', 'max_giorni'],
            array_map(fn ($r) => [(int)$r->aa_year, (int)$r->signed_records, (float)$r->avg_days, $r->min_days !== null ? (int)$r->min_days : null, $r->max_days !== null ? (int)$r->max_days : null], $durationByYear)
        );

        $this->newLine();
        $this->info('4) KPI globali processo');
        $this->table(
            [
                'conteggio_firmati',
                'media_giorni',
                'mediana_giorni',
                'p90_giorni',
                'min_giorni',
                'max_giorni',
                'tasso_completamento_pct',
                'arretrato_aperto',
                'arretrato_oltre_90_giorni',
            ],
            [[
                (int)$globalKpi->signed_count,
                $globalKpi->avg_days !== null ? (float)$globalKpi->avg_days : null,
                $globalKpi->median_days !== null ? (int)$globalKpi->median_days : null,
                $globalKpi->p90_days !== null ? (int)$globalKpi->p90_days : null,
                $globalKpi->min_days !== null ? (int)$globalKpi->min_days : null,
                $globalKpi->max_days !== null ? (int)$globalKpi->max_days : null,
                $globalKpi->completion_rate_pct !== null ? (float)$globalKpi->completion_rate_pct : null,
                (int)$globalKpi->backlog_open_count,
                (int)$globalKpi->backlog_over_90d,
            ]]
        );

        $this->newLine();
        $this->info('5) Tempi tra step processo (giorni) - Totale');
        $rowsMacro = [];
        $rowsFirmaDettaglio = [];
        foreach ($stepTimesTotal as $r) {
            $row = [
                (string)$r->step_key,
                (int)$r->record_validi,
                $r->media_giorni !== null ? (float)$r->media_giorni : null,
                $r->min_giorni !== null ? (int)$r->min_giorni : null,
                $r->max_giorni !== null ? (int)$r->max_giorni : null,
            ];

            if (in_array((string)$r->step_key, [
                'validazione_economica_to_firmaio',
                'firma_io_to_ricevuto_firmato',
                'validazione_economica_to_usign',
                'usign_to_ricevuto_firmato',
            ], true)) {
                $rowsFirmaDettaglio[] = $row;
            } else {
                $rowsMacro[] = $row;
            }
        }

        $appendPct = function (array $rows): array {
            $sumMedia = 0.0;
            $validIdx = [];
            foreach ($rows as $idx => $row) {
                if ($row[2] !== null) {
                    $sumMedia += (float)$row[2];
                    $validIdx[] = $idx;
                }
            }

            if ($sumMedia <= 0 || count($validIdx) === 0) {
                foreach ($rows as $idx => $row) {
                    $rows[$idx][] = null;
                }
                return $rows;
            }

            $acc = 0.0;
            $lastValid = end($validIdx);
            foreach ($rows as $idx => $row) {
                if ($row[2] === null) {
                    $rows[$idx][] = null;
                    continue;
                }

                if ($idx === $lastValid) {
                    $pct = round(100 - $acc, 2);
                } else {
                    $pct = round((((float)$row[2]) / $sumMedia) * 100, 2);
                    $acc += $pct;
                }
                $rows[$idx][] = $pct;
            }

            return $rows;
        };

        $rowsMacro = $appendPct($rowsMacro);
        $rowsFirmaDettaglio = $appendPct($rowsFirmaDettaglio);

        $this->line('Macro-step');
        $this->table(
            ['step', 'record_validi', 'media_giorni', 'min_giorni', 'max_giorni', 'percentuale_tempo_pct'],
            $rowsMacro
        );

        $this->line('Dettaglio step firma');
        $this->table(
            ['step', 'record_validi', 'media_giorni', 'min_giorni', 'max_giorni', 'percentuale_tempo_pct'],
            $rowsFirmaDettaglio
        );

        $this->newLine();
        $this->info('6) Tempi tra step processo (giorni) - Per anno');
        $stepTimesGrouped = [];
        foreach ($stepTimesByYear as $row) {
            $year = (int)$row->aa_year;
            if (!array_key_exists($year, $stepTimesGrouped)) {
                $stepTimesGrouped[$year] = [];
            }
            $stepTimesGrouped[$year][] = [
                (string)$row->step_key,
                (int)$row->record_validi,
                $row->media_giorni !== null ? (float)$row->media_giorni : null,
                $row->min_giorni !== null ? (int)$row->min_giorni : null,
                $row->max_giorni !== null ? (int)$row->max_giorni : null,
            ];
        }

        ksort($stepTimesGrouped);
        foreach ($stepTimesGrouped as $year => $rows) {
            $this->line("Anno AA: {$year}");
            $this->table(
                ['step', 'record_validi', 'media_giorni', 'min_giorni', 'max_giorni'],
                $rows
            );
            $this->newLine();
        }

        $this->newLine();
        $this->info('7) Data quality checks');
        $this->table(
            ['controllo', 'valore'],
            [
                ['righe_aa_non_interpretabili', (int)$dataQuality['aa_unparseable_rows']],
                ['validazioni_senza_insegnamento', (int)$dataQuality['validation_rows_without_insegnamento']],
                ['duplicati_validazione_stesso_evento', (int)$dataQuality['duplicate_validation_rows_same_event']],
                ['righe_durata_negativa', (int)$dataQuality['negative_duration_rows']],
            ]
        );

        $exportPath = $this->option('export');
        if (is_string($exportPath) && trim($exportPath) !== '') {
            $this->exportCsv($exportPath, $yearlyTrend, $durationByYear, $globalKpi, $stepTimesTotal, $stepTimesByYear, $totalSigned, $totalContracts, $fromYear, $toYear, $providers);
        }

        return self::SUCCESS;
    }

    private function resolveYearWindow(): array
    {
        $nowYear = (int)Carbon::now()->year;
        $years = max(1, (int)$this->option('years'));

        $fromYear = $this->option('from-aa') !== null ? (int)$this->option('from-aa') : ($nowYear - $years + 1);
        $toYear = $this->option('to-aa') !== null ? (int)$this->option('to-aa') : $nowYear;

        if ($fromYear > $toYear) {
            [$fromYear, $toYear] = [$toYear, $fromYear];
        }

        return [$fromYear, $toYear];
    }

    private function resolveProviders(): array
    {
        $raw = (string)$this->option('providers');
        $providers = array_values(array_filter(array_map('trim', explode(',', $raw))));
        return count($providers) > 0 ? $providers : ['FIRMAIO', 'USIGN', 'FIRMA_GRAFOMETRICA','PRESA_VISIONE'];
    }

    private function aaYearExpr(): string
    {
        return "CASE
                    WHEN p1.aa REGEXP '^[0-9]{4}$' THEN CAST(p1.aa AS UNSIGNED)
                    WHEN p1.aa REGEXP '^[0-9]{4}/[0-9]{4}$' THEN CAST(SUBSTRING_INDEX(p1.aa, '/', 1) AS UNSIGNED)
                    WHEN p1.aa REGEXP '^[0-9]{4}-[0-9]{2}$' THEN CAST(LEFT(p1.aa, 4) AS UNSIGNED)
                    ELSE YEAR(p1.created_at)
                END";
    }

    private function buildFactSql(array $providers): string
    {
        $providerList = $this->providerListForSql($providers);
        $firmaIoFirst = DB::table('richieste_firma as rf')
            ->selectRaw('rf.precontr_id, MIN(rf.created_at) AS firmaio_created_ts')
            ->groupBy('rf.precontr_id');
        $usignFirst = DB::table('processi_firma as pf')
            ->selectRaw('pf.precontr_id, MIN(pf.created_at) AS usign_created_ts')
            ->groupBy('pf.precontr_id');

        return DB::table('precontr as p')
            ->join('p1_insegnamento as p1', 'p1.id', '=', 'p.insegn_id')
            ->leftJoin('table_validation as tv', 'tv.insegn_id', '=', 'p.insegn_id')
            ->leftJoinSub($firmaIoFirst, 'fio', function ($join) {
                $join->on('fio.precontr_id', '=', 'p.id');
            })
            ->leftJoinSub($usignFirst, 'usg', function ($join) {
                $join->on('usg.precontr_id', '=', 'p.id');
            })
            ->whereRaw('p.stato < 2')
            ->selectRaw("
                p.id AS precontr_id,
                p1.id AS insegn_id,
                p1.aa AS aa_raw,
                {$this->aaYearExpr()} AS aa_year,
                p1.created_at AS insert_ts,
                tv.flag_accept AS flag_accept,
                tv.date_submit AS submit_ts,
                tv.date_upd AS upd_ts,
                tv.date_amm AS amm_ts,
                tv.date_accept AS sign_ts,
                fio.firmaio_created_ts,
                usg.usign_created_ts,
                tv.tipo_accettazione,
                CASE
                    WHEN tv.flag_accept = 1
                        AND tv.date_accept IS NOT NULL
                        AND tv.tipo_accettazione IN ({$providerList})
                    THEN 1
                    ELSE 0
                END AS is_signed,
                CASE
                    WHEN tv.flag_accept = 1
                        AND tv.date_accept IS NOT NULL
                        AND tv.tipo_accettazione IN ({$providerList})
                        AND p1.created_at IS NOT NULL
                        AND tv.date_accept >= p1.created_at
                    THEN TIMESTAMPDIFF(DAY, p1.created_at, tv.date_accept)
                    ELSE NULL
                END AS signing_days
            ")
            ->toSql();
    }

    private function providerListForSql(array $providers): string
    {
        $clean = [];
        foreach ($providers as $provider) {
            $p = strtoupper(trim((string)$provider));
            if (preg_match('/^[A-Z0-9_]+$/', $p) === 1) {
                $clean[] = "'" . $p . "'";
            }
        }

        if (count($clean) === 0) {
            $clean = ["'FIRMAIO'", "'USIGN'", "'FIRMA_GRAFOMETRICA'","'PRESA_VISIONE'"];
        }

        return implode(', ', $clean);
    }

    private function buildFactBindings(int $fromYear, int $toYear): array
    {
        return [$fromYear, $toYear];
    }

    private function queryTotalSigned(string $factSql, array $bindings): int
    {
        $sql = "
            SELECT COUNT(*) AS total_signed
            FROM ({$factSql}) f
            WHERE f.is_signed = 1
              AND f.aa_year BETWEEN ? AND ?
        ";
        $row = DB::selectOne($sql, $bindings);
        return (int)$row->total_signed;
    }

    private function queryTotalContracts(string $factSql, array $bindings): int
    {
        $sql = "
            SELECT COUNT(*) AS total_contracts
            FROM ({$factSql}) f
            WHERE f.aa_year BETWEEN ? AND ?
        ";
        $row = DB::selectOne($sql, $bindings);
        return (int)$row->total_contracts;
    }

    private function querySignedSinceFirstFirmaio(string $factSql)
    {
        $sql = "
            WITH first_firmaio AS (
                SELECT MIN(DATE(f.sign_ts)) AS first_firmaio_sign_date
                FROM ({$factSql}) f
                WHERE f.flag_accept = 1
                  AND f.sign_ts IS NOT NULL
                  AND f.tipo_accettazione = 'FIRMAIO'
            )
            SELECT
                ff.first_firmaio_sign_date,
                SUM(
                    CASE
                        WHEN f.flag_accept = 1
                         AND f.sign_ts IS NOT NULL
                         AND DATE(f.sign_ts) BETWEEN ff.first_firmaio_sign_date AND CURDATE()
                        THEN 1
                        ELSE 0
                    END
                ) AS signed_total_since_first_firmaio,
                SUM(
                    CASE
                        WHEN f.flag_accept = 1
                         AND f.sign_ts IS NOT NULL
                         AND f.tipo_accettazione = 'FIRMAIO'
                         AND DATE(f.sign_ts) BETWEEN ff.first_firmaio_sign_date AND CURDATE()
                        THEN 1
                        ELSE 0
                    END
                ) AS signed_firmaio_since_first_firmaio
            FROM first_firmaio ff
            LEFT JOIN ({$factSql}) f ON ff.first_firmaio_sign_date IS NOT NULL
        ";

        $row = DB::selectOne($sql);

        if ($row === null || $row->first_firmaio_sign_date === null) {
            return (object)[
                'first_firmaio_sign_date' => null,
                'signed_total_since_first_firmaio' => 0,
                'signed_firmaio_since_first_firmaio' => 0,
            ];
        }

        return $row;
    }

    private function queryYearlyTrend(string $factSql, array $bindings): array
    {
        $sql = "
            SELECT
                f.aa_year,
                SUM(CASE WHEN f.is_signed = 1 THEN 1 ELSE 0 END) AS signed_count,
                SUM(CASE WHEN f.is_signed = 0 THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN f.flag_accept = 1 AND f.sign_ts IS NOT NULL AND f.tipo_accettazione = 'FIRMAIO' THEN 1 ELSE 0 END) AS tot_firmaio,
                SUM(CASE WHEN f.flag_accept = 1 AND f.sign_ts IS NOT NULL AND f.tipo_accettazione = 'USIGN' THEN 1 ELSE 0 END) AS tot_usign,
                SUM(CASE WHEN f.flag_accept = 1 AND f.sign_ts IS NOT NULL AND f.tipo_accettazione = 'FIRMA_GRAFOMETRICA' THEN 1 ELSE 0 END) AS tot_firma_grafometrica
            FROM ({$factSql}) f
            WHERE f.aa_year BETWEEN ? AND ?
            GROUP BY f.aa_year
            ORDER BY f.aa_year
        ";
        return DB::select($sql, $bindings);
    }

    private function queryDurationByYear(string $factSql, array $bindings): array
    {
        $sql = "
            SELECT
                f.aa_year,
                COUNT(*) AS signed_records,
                ROUND(AVG(f.signing_days), 2) AS avg_days,
                MIN(f.signing_days) AS min_days,
                MAX(f.signing_days) AS max_days
            FROM ({$factSql}) f
            WHERE f.is_signed = 1
              AND f.signing_days IS NOT NULL
              AND f.aa_year BETWEEN ? AND ?
            GROUP BY f.aa_year
            ORDER BY f.aa_year
        ";
        return DB::select($sql, $bindings);
    }

    private function queryGlobalKpi(string $factSql, array $bindings)
    {
        $sql = "
            WITH base AS (
                SELECT *
                FROM ({$factSql}) f
                WHERE f.aa_year BETWEEN ? AND ?
            ),
            dur AS (
                SELECT signing_days
                FROM base
                WHERE is_signed = 1 AND signing_days IS NOT NULL
            ),
            ranked AS (
                SELECT signing_days, CUME_DIST() OVER (ORDER BY signing_days) AS cd
                FROM dur
            )
            SELECT
                (SELECT COUNT(*) FROM dur) AS signed_count,
                (SELECT ROUND(AVG(signing_days), 2) FROM dur) AS avg_days,
                (SELECT MIN(signing_days) FROM dur) AS min_days,
                (SELECT MAX(signing_days) FROM dur) AS max_days,
                (SELECT MIN(signing_days) FROM ranked WHERE cd >= 0.50) AS median_days,
                (SELECT MIN(signing_days) FROM ranked WHERE cd >= 0.90) AS p90_days,
                ROUND(100.0 * SUM(CASE WHEN is_signed = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 2) AS completion_rate_pct,
                SUM(CASE WHEN is_signed = 0 THEN 1 ELSE 0 END) AS backlog_open_count,
                SUM(CASE WHEN is_signed = 0 AND insert_ts < DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS backlog_over_90d
            FROM base
        ";
        return DB::selectOne($sql, $bindings);
    }

    private function queryStepTimes(string $factSql, array $bindings): array
    {
        $sql = "
            WITH base AS (
                SELECT *
                FROM ({$factSql}) f
                WHERE f.aa_year BETWEEN ? AND ?
            ),
            dur AS (
                SELECT
                    aa_year,
                    'creazione_insegnamento_to_submit_docente' AS step_key,
                    CASE
                        WHEN insert_ts IS NOT NULL AND submit_ts IS NOT NULL AND submit_ts >= insert_ts
                        THEN TIMESTAMPDIFF(DAY, insert_ts, submit_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    aa_year,
                    'compilazione_docente_to_validazione_amministrativa' AS step_key,
                    CASE
                        WHEN submit_ts IS NOT NULL AND upd_ts IS NOT NULL AND upd_ts >= submit_ts
                        THEN TIMESTAMPDIFF(DAY, submit_ts, upd_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    aa_year,
                    'validazione_amministrativa_to_validazione_economica' AS step_key,
                    CASE
                        WHEN upd_ts IS NOT NULL AND amm_ts IS NOT NULL AND amm_ts >= upd_ts
                        THEN TIMESTAMPDIFF(DAY, upd_ts, amm_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    aa_year,
                    'validazione_economica_to_firmato' AS step_key,
                    CASE
                        WHEN amm_ts IS NOT NULL
                         AND sign_ts IS NOT NULL
                         AND sign_ts >= amm_ts
                        THEN TIMESTAMPDIFF(DAY, amm_ts, sign_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    aa_year,
                    'validazione_economica_to_firmaio' AS step_key,
                    CASE
                        WHEN amm_ts IS NOT NULL
                         AND firmaio_created_ts IS NOT NULL
                         AND firmaio_created_ts >= amm_ts
                        THEN TIMESTAMPDIFF(DAY, amm_ts, firmaio_created_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    aa_year,
                    'firma_io_to_ricevuto_firmato' AS step_key,
                    CASE
                        WHEN firmaio_created_ts IS NOT NULL
                         AND sign_ts IS NOT NULL
                         AND sign_ts >= firmaio_created_ts
                         AND tipo_accettazione = 'FIRMAIO'
                        THEN TIMESTAMPDIFF(DAY, firmaio_created_ts, sign_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    aa_year,
                    'usign_to_ricevuto_firmato' AS step_key,
                    CASE
                        WHEN usign_created_ts IS NOT NULL
                         AND sign_ts IS NOT NULL
                         AND sign_ts >= usign_created_ts
                         AND tipo_accettazione = 'USIGN'
                        THEN TIMESTAMPDIFF(DAY, usign_created_ts, sign_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    aa_year,
                    'validazione_economica_to_usign' AS step_key,
                    CASE
                        WHEN amm_ts IS NOT NULL
                         AND usign_created_ts IS NOT NULL
                         AND usign_created_ts >= amm_ts
                        THEN TIMESTAMPDIFF(DAY, amm_ts, usign_created_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
            )
            SELECT
                aa_year,
                step_key,
                COUNT(giorni) AS record_validi,
                ROUND(AVG(giorni), 2) AS media_giorni,
                MIN(giorni) AS min_giorni,
                MAX(giorni) AS max_giorni
            FROM dur
            GROUP BY aa_year, step_key
            ORDER BY aa_year, FIELD(
                step_key,
                'creazione_insegnamento_to_submit_docente',
                'compilazione_docente_to_validazione_amministrativa',
                'validazione_amministrativa_to_validazione_economica',
                'validazione_economica_to_firmato',
                'validazione_economica_to_firmaio',
                'firma_io_to_ricevuto_firmato',
                'validazione_economica_to_usign',
                'usign_to_ricevuto_firmato'
            )
        ";

        return DB::select($sql, $bindings);
    }

    private function queryStepTimesTotal(string $factSql, array $bindings): array
    {
        $sql = "
            WITH base AS (
                SELECT *
                FROM ({$factSql}) f
                WHERE f.aa_year BETWEEN ? AND ?
            ),
            dur AS (
                SELECT
                    'creazione_insegnamento_to_submit_docente' AS step_key,
                    CASE
                        WHEN insert_ts IS NOT NULL AND submit_ts IS NOT NULL AND submit_ts >= insert_ts
                        THEN TIMESTAMPDIFF(DAY, insert_ts, submit_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    'compilazione_docente_to_validazione_amministrativa' AS step_key,
                    CASE
                        WHEN submit_ts IS NOT NULL AND upd_ts IS NOT NULL AND upd_ts >= submit_ts
                        THEN TIMESTAMPDIFF(DAY, submit_ts, upd_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    'validazione_amministrativa_to_validazione_economica' AS step_key,
                    CASE
                        WHEN upd_ts IS NOT NULL AND amm_ts IS NOT NULL AND amm_ts >= upd_ts
                        THEN TIMESTAMPDIFF(DAY, upd_ts, amm_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    'validazione_economica_to_firmaio' AS step_key,
                    CASE
                        WHEN amm_ts IS NOT NULL
                         AND firmaio_created_ts IS NOT NULL
                         AND firmaio_created_ts >= amm_ts
                        THEN TIMESTAMPDIFF(DAY, amm_ts, firmaio_created_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    'validazione_economica_to_firmato' AS step_key,
                    CASE
                        WHEN amm_ts IS NOT NULL
                         AND sign_ts IS NOT NULL
                         AND sign_ts >= amm_ts
                        THEN TIMESTAMPDIFF(DAY, amm_ts, sign_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    'firma_io_to_ricevuto_firmato' AS step_key,
                    CASE
                        WHEN firmaio_created_ts IS NOT NULL
                         AND sign_ts IS NOT NULL
                         AND sign_ts >= firmaio_created_ts
                         AND tipo_accettazione = 'FIRMAIO'
                        THEN TIMESTAMPDIFF(DAY, firmaio_created_ts, sign_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    'usign_to_ricevuto_firmato' AS step_key,
                    CASE
                        WHEN usign_created_ts IS NOT NULL
                         AND sign_ts IS NOT NULL
                         AND sign_ts >= usign_created_ts
                         AND tipo_accettazione = 'USIGN'
                        THEN TIMESTAMPDIFF(DAY, usign_created_ts, sign_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
                UNION ALL
                SELECT
                    'validazione_economica_to_usign' AS step_key,
                    CASE
                        WHEN amm_ts IS NOT NULL
                         AND usign_created_ts IS NOT NULL
                         AND usign_created_ts >= amm_ts
                        THEN TIMESTAMPDIFF(DAY, amm_ts, usign_created_ts)
                        ELSE NULL
                    END AS giorni
                FROM base
            )
            SELECT
                step_key,
                COUNT(giorni) AS record_validi,
                ROUND(AVG(giorni), 2) AS media_giorni,
                MIN(giorni) AS min_giorni,
                MAX(giorni) AS max_giorni
            FROM dur
            GROUP BY step_key
            ORDER BY FIELD(
                step_key,
                'creazione_insegnamento_to_submit_docente',
                'compilazione_docente_to_validazione_amministrativa',
                'validazione_amministrativa_to_validazione_economica',
                'validazione_economica_to_firmato',
                'validazione_economica_to_firmaio',
                'firma_io_to_ricevuto_firmato',
                'validazione_economica_to_usign',
                'usign_to_ricevuto_firmato'
            )
        ";

        return DB::select($sql, $bindings);
    }

    private function queryDataQuality(array $providers): array
    {
        $aaUnparseableRows = (int)DB::table('p1_insegnamento')
            ->where(function ($q) {
                $q->whereNull('aa')
                    ->orWhereRaw("NOT (aa REGEXP '^[0-9]{4}$' OR aa REGEXP '^[0-9]{4}/[0-9]{4}$' OR aa REGEXP '^[0-9]{4}-[0-9]{2}$')");
            })
            ->count();

        $validationWithoutInsegnamento = (int)DB::table('table_validation as tv')
            ->leftJoin('p1_insegnamento as p1', 'p1.id', '=', 'tv.insegn_id')
            ->whereNull('p1.id')
            ->count();

        $duplicateValidationRowsSameEvent = (int)DB::table('table_validation as tv')
            ->selectRaw('tv.insegn_id, tv.tipo_accettazione, tv.date_accept, COUNT(*) AS c')
            ->whereIn('tv.tipo_accettazione', $providers)
            ->whereNotNull('tv.date_accept')
            ->groupBy('tv.insegn_id', 'tv.tipo_accettazione', 'tv.date_accept')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $negativeDurationRows = (int)DB::table('p1_insegnamento as p1')
            ->join('table_validation as tv', 'tv.insegn_id', '=', 'p1.id')
            ->where('tv.flag_accept', 1)
            ->whereIn('tv.tipo_accettazione', $providers)
            ->whereNotNull('tv.date_accept')
            ->whereNotNull('p1.created_at')
            ->whereRaw('tv.date_accept < p1.created_at')
            ->count();

        return [
            'aa_unparseable_rows' => $aaUnparseableRows,
            'validation_rows_without_insegnamento' => $validationWithoutInsegnamento,
            'duplicate_validation_rows_same_event' => $duplicateValidationRowsSameEvent,
            'negative_duration_rows' => $negativeDurationRows,
        ];
    }

    private function exportCsv(
        string $relativePath,
        array $yearlyTrend,
        array $durationByYear,
        $globalKpi,
        array $stepTimesTotal,
        array $stepTimesByYear,
        int $totalSigned,
        int $totalContracts,
        int $fromYear,
        int $toYear,
        array $providers
    ): void {
        $lines = [];
        $lines[] = 'section,key,value';
        $lines[] = sprintf('meta,from_aa,%d', $fromYear);
        $lines[] = sprintf('meta,to_aa,%d', $toYear);
        $lines[] = sprintf('meta,providers,"%s"', implode(',', $providers));
        $lines[] = sprintf('total,total_contracts,%d', $totalContracts);
        $lines[] = sprintf('total,signed_contracts,%d', $totalSigned);
        $lines[] = sprintf('global_kpi,signed_count,%d', (int)$globalKpi->signed_count);
        $lines[] = sprintf('global_kpi,avg_days,%s', $globalKpi->avg_days);
        $lines[] = sprintf('global_kpi,median_days,%s', $globalKpi->median_days);
        $lines[] = sprintf('global_kpi,p90_days,%s', $globalKpi->p90_days);
        $lines[] = sprintf('global_kpi,min_days,%s', $globalKpi->min_days);
        $lines[] = sprintf('global_kpi,max_days,%s', $globalKpi->max_days);
        $lines[] = sprintf('global_kpi,completion_rate_pct,%s', $globalKpi->completion_rate_pct);
        $lines[] = sprintf('global_kpi,backlog_open_count,%d', (int)$globalKpi->backlog_open_count);
        $lines[] = sprintf('global_kpi,backlog_over_90d,%d', (int)$globalKpi->backlog_over_90d);

        foreach ($yearlyTrend as $row) {
            $lines[] = sprintf('yearly_trend,%d_signed_count,%d', (int)$row->aa_year, (int)$row->signed_count);
            $lines[] = sprintf('yearly_trend,%d_open_count,%d', (int)$row->aa_year, (int)$row->open_count);
            $lines[] = sprintf('yearly_trend,%d_tot_firmaio,%d', (int)$row->aa_year, (int)$row->tot_firmaio);
            $lines[] = sprintf('yearly_trend,%d_tot_usign,%d', (int)$row->aa_year, (int)$row->tot_usign);
            $lines[] = sprintf('yearly_trend,%d_tot_firma_grafometrica,%d', (int)$row->aa_year, (int)$row->tot_firma_grafometrica);
        }

        foreach ($durationByYear as $row) {
            $lines[] = sprintf('duration_by_year,%d_avg_days,%s', (int)$row->aa_year, $row->avg_days);
            $lines[] = sprintf('duration_by_year,%d_min_days,%s', (int)$row->aa_year, $row->min_days);
            $lines[] = sprintf('duration_by_year,%d_max_days,%s', (int)$row->aa_year, $row->max_days);
        }

        foreach ($stepTimesTotal as $row) {
            $prefix = (string)$row->step_key;
            $lines[] = sprintf('step_times_total,%s_record_validi,%d', $prefix, (int)$row->record_validi);
            $lines[] = sprintf('step_times_total,%s_media_giorni,%s', $prefix, $row->media_giorni);
            $lines[] = sprintf('step_times_total,%s_min_giorni,%s', $prefix, $row->min_giorni);
            $lines[] = sprintf('step_times_total,%s_max_giorni,%s', $prefix, $row->max_giorni);
        }
        $sommaMedieStepTotale = 0.0;
        foreach ($stepTimesTotal as $row) {
            if ($row->media_giorni !== null) {
                $sommaMedieStepTotale += (float)$row->media_giorni;
            }
        }
        foreach ($stepTimesTotal as $row) {
            $prefix = (string)$row->step_key;
            $pct = ($sommaMedieStepTotale > 0 && $row->media_giorni !== null)
                ? round(((float)$row->media_giorni / $sommaMedieStepTotale) * 100, 2)
                : null;
            $lines[] = sprintf('step_times_total,%s_percentuale_tempo_pct,%s', $prefix, $pct);
        }

        foreach ($stepTimesByYear as $row) {
            $prefix = (int)$row->aa_year . '_' . (string)$row->step_key;
            $lines[] = sprintf('step_times_by_year,%s_record_validi,%d', $prefix, (int)$row->record_validi);
            $lines[] = sprintf('step_times_by_year,%s_media_giorni,%s', $prefix, $row->media_giorni);
            $lines[] = sprintf('step_times_by_year,%s_min_giorni,%s', $prefix, $row->min_giorni);
            $lines[] = sprintf('step_times_by_year,%s_max_giorni,%s', $prefix, $row->max_giorni);
        }

        Storage::disk('local')->put($relativePath, implode(PHP_EOL, $lines) . PHP_EOL);
        $this->info("CSV salvato in storage/app/{$relativePath}");
    }
}
