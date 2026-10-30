<?php
require_once __DIR__ . '/player_characters.php';

$selectedCharacter = (string)($selectedCharacter ?? default_player_character());
$selectedAccessory = (string)($selectedAccessory ?? '');
$pickerCharacters = player_character_catalog();
$pickerAccessories = player_accessory_catalog();
?>
<section class="character-editor" data-character-editor aria-label="Customize your character">
    <div class="character-preview" data-character-preview>
        <?= player_character_image($selectedCharacter, 'character-preview-avatar', $selectedAccessory) ?>
    </div>
    <p class="character-summary" data-character-summary role="status" aria-live="polite"><?= htmlspecialchars(player_character_features($selectedCharacter)[0]) ?><?= $selectedAccessory !== '' && isset($pickerAccessories[$selectedAccessory]) ? ' · ' . htmlspecialchars($pickerAccessories[$selectedAccessory]['name']) : '' ?></p>
    <div class="character-tabs" role="tablist" aria-label="Customization options" hidden>
        <button type="button" role="tab" id="charactersTab" aria-controls="charactersPanel" aria-selected="true" data-character-tab="charactersPanel">Characters</button>
        <button type="button" role="tab" id="accessoriesTab" aria-controls="accessoriesPanel" aria-selected="false" tabindex="-1" data-character-tab="accessoriesPanel">Accessories</button>
    </div>
    <fieldset class="character-picker" id="charactersPanel">
        <legend>Choose your character</legend>
        <div class="character-options">
            <?php foreach ($pickerCharacters as $characterId => $path): $characterName = player_character_features((string) $characterId)[0]; ?>
                <label class="character-option" title="<?= htmlspecialchars($characterName) ?>">
                    <input type="radio" name="character_id" value="<?= $characterId ?>" data-name="<?= htmlspecialchars($characterName) ?>" <?= (string) $characterId === $selectedCharacter ? 'checked' : '' ?> required aria-label="<?= htmlspecialchars($characterName) ?>">
                    <span class="character-option-art"><?= player_character_image((string) $characterId) ?><span class="character-selected" aria-hidden="true">&#10003;</span></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <fieldset class="character-picker" id="accessoriesPanel">
        <legend>Choose an accessory</legend>
        <div class="character-options accessory-options">
            <label class="character-option" title="No accessory">
                <input type="radio" name="accessory_id" value="" data-name="No accessory" data-src="" <?= $selectedAccessory === '' ? 'checked' : '' ?> aria-label="No accessory">
                <span class="character-option-art"><span class="accessory-none" aria-hidden="true">&#8709;<small>None</small></span><span class="character-selected" aria-hidden="true">&#10003;</span></span>
            </label>
            <?php foreach ($pickerAccessories as $accessoryId => $accessory): ?>
                <label class="character-option" title="<?= htmlspecialchars($accessory['name']) ?>">
                    <input type="radio" name="accessory_id" value="<?= $accessoryId ?>" data-name="<?= htmlspecialchars($accessory['name']) ?>" data-src="<?= htmlspecialchars(BASE_URL . $accessory['path'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) $accessoryId === $selectedAccessory ? 'checked' : '' ?> aria-label="<?= htmlspecialchars($accessory['name']) ?>">
                    <span class="character-option-art"><img src="<?= htmlspecialchars(BASE_URL . $accessory['path'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="60" height="60" loading="lazy"><span class="character-selected" aria-hidden="true">&#10003;</span></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <button type="button" class="character-random" data-character-random hidden>Surprise me!</button>
</section>
