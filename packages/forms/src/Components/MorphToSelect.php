<?php

namespace Filament\Forms\Components;

use Closure;
use Exception;
use Filament\Forms\Components\Concerns\HasName;
use Filament\Forms\Components\MorphToSelect\Type;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use function Filament\Support\get_model_label;

class MorphToSelect extends Component
{
    use Concerns\CanAllowHtml;
    use Concerns\CanBePreloaded;
    use Concerns\CanBeSearchable;
    use Concerns\HasLoadingMessage;
    use Concerns\HasName;

    protected string $view = 'forms::components.fieldset';

    public bool | Closure $isRequired = false;

    protected int | Closure $optionsLimit = 50;

    public array | Closure $types = [];

    final public function __construct(string $name)
    {
        $this->name($name);
    }

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public function getChildComponents(): array
    {
        $relationship = $this->getRelationship();
        $typeColumn = $relationship->getMorphType();
        $keyColumn = $relationship->getForeignKeyName();

        $types = $this->getTypes();
        $isRequired = $this->isRequired();

        /** @var Type | null $selectedType */
        $selectedType = $types[$this->evaluate(fn (Closure $get): ?string => $get($typeColumn))] ?? null;

        return [
            Select::make($typeColumn)
                ->label('Type')
                ->disableLabel()
                ->options(array_map(
                    fn (Type $type): string => $type->getLabel(),
                    $types,
                ))
                ->required($isRequired)
                ->reactive()
                ->afterStateUpdated(fn (Closure $set) => $set($keyColumn, null)),
            Select::make($keyColumn)
                ->label($selectedType?->getLabel())
                ->disableLabel()
                ->options($selectedType?->getOptionsUsing)
                ->getSearchResultsUsing($selectedType?->getSearchResultsUsing)
                ->getOptionLabelUsing($selectedType?->getOptionLabelUsing)
                ->required($isRequired)
                ->hidden(! $selectedType)
                ->searchable($this->isSearchable())
                ->searchDebounce($this->getSearchDebounce())
                ->searchPrompt($this->getSearchPrompt())
                ->searchingMessage($this->getSearchingMessage())
                ->noSearchResultsMessage($this->getNoSearchResultsMessage())
                ->loadingMessage($this->getLoadingMessage())
                ->allowHtml($this->isHtmlAllowed())
                ->optionsLimit($this->getOptionsLimit())
                ->preload($this->isPreloaded()),
        ];
    }

    public function optionsLimit(int | Closure $limit): static
    {
        $this->optionsLimit = $limit;

        return $this;
    }

    public function required(bool | Closure $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    public function types(array | Closure $types): static
    {
        $this->types = $types;

        return $this;
    }

    public function getLabel(): string | Htmlable | null
    {
        $label = parent::getLabel() ?? (string) Str::of($this->getName())
            ->afterLast('.')
            ->kebab()
            ->replace(['-', '_'], ' ')
            ->ucfirst();

        return (is_string($label) && $this->shouldTranslateLabel) ?
            __($label) :
            $label;
    }

    public function getRelationship(): MorphTo
    {
        return $this->getModelInstance()->{$this->getName()}();
    }

    public function getTypes(): array
    {
        $types = [];

        foreach ($this->evaluate($this->types) as $type) {
            $types[$type->getAlias()] = $type;
        }

        return $types;
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->isRequired);
    }

    public function getOptionsLimit(): int
    {
        return $this->evaluate($this->optionsLimit);
    }
}
