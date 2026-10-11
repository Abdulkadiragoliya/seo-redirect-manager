<?php

namespace abdulkadir\seoredirectmanager\services;

use Craft;
use craft\db\Query;
use yii\base\Component;
use abdulkadir\seoredirectmanager\db\Table;

/**
 * Service detecting multi-hop redirect chains (e.g. /old -> /middle -> /new)
 * and suggesting direct one-hop optimization.
 */
class RedirectChainService extends Component
{
    /**
     * Scans exact rules and identifies multi-hop redirect chains.
     *
     * @return array List of chain structures with hop details and suggested direct destinations.
     */
    public function findChains(?int $siteId = null): array
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

        // Build lookup map by source normalized
        $sourceMap = [];
        foreach ($rules as $rule) {
            $key = ($rule['siteId'] ?? 'all') . ':' . $rule['sourceNormalized'];
            $sourceMap[$key] = $rule;

            // Also fallback for shared lookup
            $sharedKey = 'all:' . $rule['sourceNormalized'];
            if (!isset($sourceMap[$sharedKey])) {
                $sourceMap[$sharedKey] = $rule;
            }
        }

        $chains = [];

        foreach ($rules as $initialRule) {
            $currentRule = $initialRule;
            $hops = [$initialRule];
            $visitedIds = [$initialRule['id'] => true];
            $chainLength = 1;
            $maxHops = 10;

            while ($chainLength < $maxHops) {
                $destPath = parse_url($currentRule['destination'], PHP_URL_PATH) ?? $currentRule['destination'];
                $destNormalized = trim(mb_strtolower($destPath, 'UTF-8'), '/');

                if (empty($destNormalized)) {
                    break;
                }

                // Check next rule in chain
                $siteKey = ($currentRule['siteId'] ?? 'all') . ':' . $destNormalized;
                $sharedKey = 'all:' . $destNormalized;

                $nextRule = $sourceMap[$siteKey] ?? ($sourceMap[$sharedKey] ?? null);

                if ($nextRule && !isset($visitedIds[$nextRule['id']])) {
                    $hops[] = $nextRule;
                    $visitedIds[$nextRule['id']] = true;
                    $currentRule = $nextRule;
                    $chainLength++;
                } else {
                    break;
                }
            }

            // A chain exists if there are 2 or more hops and does not end in a loop
            if ($chainLength >= 2) {
                $finalHop = end($hops);
                $destPath = parse_url($finalHop['destination'], PHP_URL_PATH) ?? $finalHop['destination'];
                $destNorm = trim(mb_strtolower($destPath, 'UTF-8'), '/');

                // Check if final destination loops back to any hop in the chain
                $isLoop = false;
                foreach ($hops as $h) {
                    if ($h['sourceNormalized'] === $destNorm) {
                        $isLoop = true;
                        break;
                    }
                }

                if ($isLoop) {
                    continue; // Skip loops; they belong in the Loops report
                }
                $steps = array_map(fn($h) => $h['source'], $hops);
                $steps[] = $finalHop['destination'];

                $chains[] = [
                    'start_id' => $initialRule['id'],
                    'target' => $finalHop['destination'],
                    'length' => $chainLength,
                    'steps' => $steps,
                    'initialRuleId' => $initialRule['id'],
                    'initialSource' => $initialRule['source'],
                    'finalDestination' => $finalHop['destination'],
                    'hopCount' => $chainLength,
                    'hops' => $hops,
                    'suggestedDirect' => [
                        'source' => $initialRule['source'],
                        'destination' => $finalHop['destination'],
                        'statusCode' => $initialRule['statusCode'],
                    ],
                ];
            }
        }

        return $chains;
    }
}
