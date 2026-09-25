<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectKind;
use App\Enums\VersioningMode;
use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['md' => 2])
            ->components([
                Section::make('Identity')
                    ->columns(['md' => 2])
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('slug')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            // Documentation owns the root of the site, so a
                            // slug occupies the same position as every other
                            // top-level path. The router excludes the reserved
                            // ones by whole segment; refusing them here means
                            // the clash is a validation message rather than a
                            // project that registers and then never resolves.
                            ->rule(Rule::notIn(self::reservedPaths()))
                            ->validationMessages([
                                'not_in' => 'That is a reserved root path. Documentation is served from the root of the site, so a project cannot take one.',
                            ])
                            ->helperText('The first URL segment: /curator/1.x/installation.'),
                        TextInput::make('name')
                            ->required()
                            ->helperText('As a reader should see it — "Curator".'),
                        Select::make('kind')
                            ->options(ProjectKind::class)
                            ->default(ProjectKind::Package)
                            ->required(),
                        TextInput::make('group')
                            ->required()
                            ->datalist(self::existingGroups())
                            ->helperText('Groups the project on the homepage. Packages, Applications, Tools.'),
                        Textarea::make('description')
                            ->columnSpanFull()
                            ->rows(2)
                            ->helperText('One line, shown beside the project on the homepage.'),
                    ]),

                Section::make('Repository')
                    ->columns(['md' => 2])
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('repository')
                            ->required()
                            ->regex('/^[\w.-]+\/[\w.-]+$/')
                            ->helperText('owner/name, as GitHub spells it — awcodes/filament-curator.'),
                        TextInput::make('docs_path')
                            ->required()
                            ->default('docs')
                            ->helperText('Where docs.yml lives. A version without it publishes the README as one page.'),
                        TextInput::make('default_branch')
                            ->required()
                            ->helperText('The repository\'s main branch. Each version still reads its own ref.'),
                        Select::make('versioning_mode')
                            ->options(VersioningMode::class)
                            ->default(VersioningMode::Versioned)
                            ->required()
                            ->helperText('Rolling projects have exactly one version and no version segment in their URLs.'),
                    ]),

                Section::make('Visibility')
                    ->columns(['md' => 2])
                    ->columnSpanFull()
                    ->components([
                        Toggle::make('is_visible')
                            ->default(true)
                            ->helperText('Off keeps it out of the homepage and the selector. It stays reachable by direct URL, which is what a project being set up needs.'),
                    ]),
            ]);
    }

    /**
     * Root paths the application has already spoken for.
     *
     * @return list<string>
     */
    private static function reservedPaths(): array
    {
        /** @var list<string> $reserved */
        $reserved = config('documentation.reserved_paths', []);

        return $reserved;
    }

    /**
     * The groups already in use, offered as suggestions rather than as options.
     *
     * A select would make adding the fourth group a code change.
     *
     * @return list<string>
     */
    private static function existingGroups(): array
    {
        return Project::query()
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->all();
    }
}
