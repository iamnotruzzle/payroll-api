<?php

namespace App\Support\Hris;

class HrisCutover
{
    /** @var array<string, string> */
    public const MODULE_LABELS = [
        'employees' => 'Employees',
        'leave' => 'Leave',
        'self_service' => 'Self-service',
        'schedule' => 'Schedule',
        'training' => 'Training',
        'performance' => 'Performance',
    ];

    public static function moduleComplete(string $module): bool
    {
        return (bool) config("hris.cutover.{$module}", false);
    }

    /**
     * @return list<string> Config keys that are true.
     */
    public static function completedModuleKeys(): array
    {
        $flags = config('hris.cutover', []);

        if (! is_array($flags)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($flags),
            fn (string $key) => (bool) ($flags[$key] ?? false)
        ));
    }

    /**
     * @return list<string> Human labels for completed modules.
     */
    public static function completedModuleLabels(): array
    {
        return array_values(array_filter(array_map(
            fn (string $key) => self::MODULE_LABELS[$key] ?? $key,
            self::completedModuleKeys()
        )));
    }

    public static function freezeLegacyWrites(): bool
    {
        return (bool) config('hris.freeze_legacy_writes', false);
    }

    public static function usesV2(): bool
    {
        return (bool) config('hris.use_v2', false);
    }

    /**
     * Optional ERP shell banner when at least one module is cutover-complete.
     */
    public static function bannerMessage(): ?string
    {
        $labels = self::completedModuleLabels();

        if ($labels === []) {
            return null;
        }

        $list = count($labels) === 1
            ? $labels[0]
            : (implode(', ', array_slice($labels, 0, -1)).' and '.$labels[array_key_last($labels)]);

        return "Use this app — legacy HRIS retired for {$list}.";
    }
}
