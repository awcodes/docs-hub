<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Documentation\Search\DocumentationSearch;
use App\Documentation\Search\SearchResult;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The command-dialog search.
 *
 * Livewire rather than a client-side index: the index holds the full text of
 * every project, and shipping all of it to the browser on every page would
 * cost far more than asking the server.
 *
 * The dialog is a `<dialog>` element, so focus trapping, Escape and the
 * backdrop are the browser's job rather than ours.
 *
 * Whether it is open is deliberately not state here. It is client-side UI the
 * server has no use for, and entangling it bought a round-trip per keystroke
 * and a morph that fought the element's own `open` attribute.
 */
class SearchDialog extends Component
{
    #[Url(as: 'q', history: true, except: '')]
    public string $query = '';

    /**
     * Results for the current query.
     *
     * A computed property rather than state: it is derived from `$query` and
     * nothing else, and holding a copy would mean keeping the two in step.
     *
     * @return list<SearchResult>
     */
    public function results(DocumentationSearch $search): array
    {
        return mb_strlen(mb_trim($this->query)) < 2
            ? []
            : $search->search($this->query);
    }

    public function render(DocumentationSearch $search): View
    {
        return view('livewire.search-dialog', [
            'results' => $this->results($search),
        ]);
    }
}
