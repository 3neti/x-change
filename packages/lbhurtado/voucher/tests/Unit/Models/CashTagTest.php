<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Models\Cash;
use Spatie\Tags\Tag;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cash = Cash::factory()->create(); // Use factory to create a Cash instance
});

it('can create a cash model with tags', function () {
    $cash = Cash::factory()->create();
    $cash->attachTags(['finance', 'investment']);

    expect($cash->tags->pluck('name')->toArray())
        ->toContain('finance', 'investment');
});

it('can attach tags to a cash model', function () {
    $this->cash->attachTag('budget');
    expect($this->cash->tags->pluck('name')->toArray())->toContain('budget');

    $this->cash->attachTags(['saving', 'planning']);
    $this->cash->refresh();
    expect($this->cash->tags->pluck('name')->toArray())
        ->toContain('saving', 'planning');
});

it('can detach tags from a cash model', function () {
    $this->cash->attachTags(['removable', 'permanent']);
    $this->cash->detachTag('removable');

    expect($this->cash->tags->pluck('name')->toArray())
        ->not->toContain('removable')
        ->toContain('permanent');
});

it('can sync tags on a cash model', function () {
    $this->cash->syncTags(['income', 'expense']);
    expect($this->cash->tags->pluck('name')->toArray())
        ->toContain('income', 'expense');

    $this->cash->syncTags(['savings']);
    $this->cash->refresh();
    expect($this->cash->tags->pluck('name')->toArray())
        ->toContain('savings')
        ->not->toContain('income', 'expense'); // Old tags are removed
});

it('can sync tags with a type on a cash model', function () {
    $this->cash->syncTagsWithType(['paycheck', 'bonus'], 'income');
    $this->cash->syncTagsWithType(['groceries', 'bills'], 'expense');

    expect($this->cash->tagsWithType('income')->pluck('name')->toArray())
        ->toContain('paycheck', 'bonus')
        ->and($this->cash->tagsWithType('expense')->pluck('name')->toArray())
        ->toContain('groceries', 'bills');
});

it('retrieves cash models with specific tags', function () {
    $this->cash->attachTags(['taggable']);
    Cash::factory()->create()->attachTags(['different']);

    $taggedCash = Cash::withAnyTags(['taggable'])->get();
    $differentlyTagged = Cash::withAnyTags(['different'])->get();

    expect($taggedCash)->toHaveCount(1)
        ->and($taggedCash->pluck('id')->toArray())->toContain($this->cash->id)
        ->and($differentlyTagged)->toHaveCount(1)
        ->and($differentlyTagged->pluck('tags')->toArray())->not->toContain($this->cash->tags);

});

it('retrieves cash models that have all specified tags', function () {
    $this->cash->syncTags(['tag1', 'tag2']);
    Cash::factory()->create()->syncTags(['tag1']); // Missing one tag

    $cashWithAllTags = Cash::withAllTags(['tag1', 'tag2'])->get();
    expect($cashWithAllTags)->toHaveCount(1)
        ->and($cashWithAllTags->pluck('id')->toArray())->toContain($this->cash->id);
});

it('retrieves cash models without specific tags', function () {
    $this->cash->syncTags(['keepable']);
    Cash::factory()->create()->syncTags(['excluded']);

    $cashWithoutSpecificTag = Cash::withoutTags(['excluded'])->get();
    expect($cashWithoutSpecificTag)
        ->toHaveCount(1)
        ->and($cashWithoutSpecificTag->pluck('id')->toArray())->toContain($this->cash->id);
});

it('can retrieve translated tags', function () {
    $tag = Tag::findOrCreate('example tag');
    $tag->setTranslation('name', 'fr', 'balise exemple');
    $tag->save();

    $this->cash->attachTag('example tag');

    app()->setLocale('fr');
    $translatedTags = $this->cash->tagsTranslated()->pluck('name')->toArray();

    expect($translatedTags)->toContain('balise exemple');
});

it('checks if the cash model has specific tags', function () {
    $this->cash->syncTags(['important tag']);

    expect($this->cash->hasTag('important tag'))->toBeTrue()
        ->and($this->cash->hasTag('non-existent tag'))->toBeFalse();
});

it('manages the order of tags attached to a cash model', function () {
    // Attach tags to the Cash model
    $this->cash->attachTags(['first', 'second']);

    // Retrieve the attached tags and sort them by the `order_column`
    $tags = $this->cash->tags->sortBy('order_column')->values();

    // Assert that the tags are attached in the correct order
    expect($tags[0]->name)->toEqual('first')
        ->and($tags[1]->name)->toEqual('second');

    // Swap their positions manually in the database
    $tags[0]->update(['order_column' => 2]);
    $tags[1]->update(['order_column' => 1]);

    // Reload and sort the tags by their updated order
    $reloadedTags = $this->cash->fresh()->tags->sortBy('order_column')->values();

    // Assert that the tag order has been reversed
    expect($reloadedTags[0]->name)->toEqual('second')
        ->and($reloadedTags[1]->name)->toEqual('first');
});
