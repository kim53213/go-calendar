<?php

declare(strict_types=1);

namespace Console\Commands;

use Console\Enums\OutputGroup;
use Console\Services\OutputService;
use Console\Services\TranslationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildPokemonNames extends Command
{
    /**
     * Pokémon species names in every language, from the PokeAPI data set.
     */
    protected const SPECIES_NAMES_URL = 'https://raw.githubusercontent.com/PokeAPI/pokeapi/master/data/v2/csv/pokemon_species_names.csv';

    /**
     * PokeAPI `local_language_id` values.
     */
    protected const ENGLISH_LANGUAGE_ID = '9';

    protected const LOCALE_LANGUAGE_IDS = [
        'zh-Hant' => '4',
        'zh-Hans' => '12',
    ];

    protected function configure()
    {
        $this
            ->setName(name: 'pokemon-names')
            ->setDescription(description: 'Builds the English to translated Pokémon name tables from PokeAPI')
            ->setHelp(help: 'Builds the English to translated Pokémon name tables from PokeAPI');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new OutputService(output: $output);

        $output->msg(
            group: OutputGroup::START,
            message: 'Building Pokémon name tables.'
        );

        $rows = array_map(
            callback: fn (string $line): array => str_getcsv(string: $line, escape: '\\'),
            array: file(
                filename: self::SPECIES_NAMES_URL,
                flags: FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            )
        );

        // species id => language id => name
        $names = [];

        foreach (array_slice(array: $rows, offset: 1) as [$speciesId, $languageId, $name]) {
            $names[$speciesId][$languageId] = $name;
        }

        foreach (self::LOCALE_LANGUAGE_IDS as $locale => $languageId) {
            $table = [];

            foreach ($names as $speciesNames) {
                $english = $speciesNames[self::ENGLISH_LANGUAGE_ID] ?? null;
                $translated = $speciesNames[$languageId] ?? null;

                if ($english === null || $translated === null) {
                    continue;
                }

                $table[$english] = $translated;

                // Leek Duck uses a straight apostrophe, PokeAPI a curly one (Farfetch’d, Sirfetch’d)
                if (str_contains(haystack: $english, needle: '’')) {
                    $table[str_replace(search: '’', replace: "'", subject: $english)] = $translated;
                }
            }

            ksort(array: $table);

            $filepath = TranslationService::BASE_PATH . "{$locale}/pokemon.json";

            if (! is_dir(filename: dirname(path: $filepath))) {
                mkdir(directory: dirname(path: $filepath), recursive: true);
            }

            file_put_contents(
                filename: $filepath,
                data: json_encode(
                    value: $table,
                    flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                ) . "\n"
            );

            $output->msg(
                group: OutputGroup::EXPORT,
                message: 'Wrote ' . count(value: $table) . " names to {$locale}/pokemon.json"
            );
        }

        $output->msg(
            group: OutputGroup::END,
            message: 'Pokémon name tables complete!'
        );

        return Command::SUCCESS;
    }
}
