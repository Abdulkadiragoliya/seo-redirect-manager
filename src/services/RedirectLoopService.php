<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;

/**
 * Service detecting direct and indirect redirect loops (cycles in redirect graph).
 */
class RedirectLoopService extends Component
{
    /**
     * Scans exact rules and identifies cyclic redirect loops.
     *
     * @return array List of discovered loops with cycle paths.
     */
    public function findLoops(?int $siteId = null): array
    {
        $query = (new Query())
            ->select(['id', 'siteId', 'source', 'sourceNormalized', 'destination', 'statusCode', 'matchType', 'description'])
            ->from(Table::REDIRECTS)
            ->where(['matchType' => 'exact', 'enabled' => true]);

        if ($siteId !== null) {
            $query->andWhere(['or', ['siteId' => $siteId], ['siteId' => null]]);
        }

        $rules = $query->all();
        if (empty($rules)) {
            return [];
        }

        $sourceMap = [];
        foreach ($rules as $rule) {
            $key = ($rule['siteId'] ?? 'all') . ':' . $rule['sourceNormalized'];
            $sourceMap[$key] = $rule;
            $sharedKey = 'all:' . $rule['sourceNormalized'];
            if (!isset($sourceMap[$sharedKey])) {
                $sourceMap[$sharedKey] = $rule;
            }
        }

        $loops = [];
        $reportedCycles = [];

        foreach ($rules as $rule) {
            $path = [$rule];
            $visited = [$rule['id'] => 0];
            $current = $rule;

            for ($i = 0; $i < 15; $i++) {
                $destPath = parse_url($current['destination'], PHP_URL_PATH) ?? $current['destination'];
                $destNormalized = trim(mb_strtolower($destPath, 'UTF-8'), '/');

                if (empty($destNormalized)) {
                    break;
                }

                $siteKey = ($current['siteId'] ?? 'all') . ':' . $destNormalized;
                $sharedKey = 'all:' . $destNormalized;

                $nextRule = $sourceMap[$siteKey] ?? ($sourceMap[$sharedKey] ?? null);

                if (!$nextRule) {
                    break;
                }

                if (isset($visited[$nextRule['id']])) {
                    // Cycle detected!
                    $cycleStartIndex = $visited[$nextRule['id']];
                    $cycleHops = array_slice($path, $cycleStartIndex);
                    $cycleHops[] = $nextRule;

                    $cycleIds = array_column($cycleHops, 'id');
                    sort($cycleIds);
                    $cycleSignature = implode('-', $cycleIds);

                    if (!isset($reportedCycles[$cycleSignature])) {
                        $reportedCycles[$cycleSignature] = true;
                        $cycleSteps = array_map(fn($h) => $h['source'], $cycleHops);
                        $lastHop = end($cycleHops);
                        if ($lastHop && !empty($lastHop['destination'])) {
                            $cycleSteps[] = $lastHop['destination'];
                        }

                        $loops[] = [
                            'start_id' => $rule['id'],
                            'ruleId' => $rule['id'],
                            'cycleLength' => count($cycleHops) - 1,
                            'cycle' => $cycleSteps,
                            'cycleHops' => $cycleHops,
                            'summary' => implode(' &rarr; ', $cycleSteps),
                        ];
                    }
                    break;
                }

                $visited[$nextRule['id']] = count($path);
                $path[] = $nextRule;
                $current = $nextRule;
            }
        }

        return $loops;
    }

    /**
     * Checks if proposing a redirect from $source to $destination would create an indirect cycle.
     */
    public function wouldCreateLoop(string $source, string $destination, ?int $siteId = null, ?int $excludeId = null): bool
    {
        $sourceNorm = trim(mb_strtolower(parse_url($source, PHP_URL_PATH) ?? $source, 'UTF-8'), '/');
        $destNorm = trim(mb_strtolower(parse_url($destination, PHP_URL_PATH) ?? $destination, 'UTF-8'), '/');

        // Direct self-loop
        if ($sourceNorm === $destNorm) {
            return true;
        }

        // Trace forward from destination
        $query = (new Query())
            ->select(['id', 'sourceNormalized', 'destination'])
            ->from(Table::REDIRECTS)
            ->where(['matchType' => 'exact', 'enabled' => true]);

        if ($siteId !== null) {
            $query->andWhere(['or', ['siteId' => $siteId], ['siteId' => null]]);
        }
        if ($excludeId !== null) {
            $query->andWhere(['not', ['id' => $excludeId]]);
        }

        $rules = $query->all();
        $map = [];
        foreach ($rules as $r) {
            $map[$r['sourceNormalized']] = $r['destination'];
        }

        $current = $destNorm;
        $visited = [$sourceNorm => true];

        for ($hop = 0; $hop < 10; $hop++) {
            if ($current === $sourceNorm) {
                return true; // Loop back to original source!
            }

            if (isset($visited[$current])) {
                return true;
            }

            if (!isset($map[$current])) {
                break;
            }

            $visited[$current] = true;
            $nextDest = $map[$current];
            $current = trim(mb_strtolower(parse_url($nextDest, PHP_URL_PATH) ?? $nextDest, 'UTF-8'), '/');
        }

        return false;
    }
}
