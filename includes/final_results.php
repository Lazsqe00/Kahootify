<?php
// Shared by the host and player when the room has finished. All queries are read-only.
require_once __DIR__ . '/player_characters.php';
$resultsPlayerId = $resultsPlayerId ?? 0;
$isHostResults = $resultsPlayerId === 0;
$quiz = db_fetch_one('SELECT name FROM quiz WHERE id_quiz = :q', [':q' => $room['id_quiz']]);
$questionCount = db_count('questions', 'id_quiz = :q', [':q' => $room['id_quiz']]);
$participantCount = count($leaderboard);
$myResult = null;
$myRank = null;
$resultCharacters = [];
foreach ($leaderboard as $entry) {
    $resultCharacters[$entry['id_player']] = get_player_appearance($room, (int) $entry['id_player']);
}
foreach ($leaderboard as $index => $entry) {
    if ((int) $entry['id_player'] === $resultsPlayerId) {
        $myResult = $entry;
        $myRank = $index + 1;
        break;
    }
}
$pageTitle = 'Final Results - ' . ($quiz['name'] ?? 'Kahootify');
$bodyClass = 'final-results-page';
$pageStyles = ['assets/css/final-results.css'];
$rankSuffix = static function (int $rank): string {
    if ($rank % 100 >= 11 && $rank % 100 <= 13) return 'th';
    return [1 => 'st', 2 => 'nd', 3 => 'rd'][$rank % 10] ?? 'th';
};
$renderMedal = static function (int $rank): string {
    return '<svg class="results-medal medal-rank-' . min($rank, 4) . '" viewBox="0 0 100 110" aria-hidden="true" focusable="false">'
        . '<path class="medal-edge" d="M23 4h54l20 64-47 38L3 68z"/>'
        . '<path class="medal-face" d="M26 9h48l17 57-41 33L9 66z"/>'
        . '<path class="medal-light" d="M26 9l9 17-11 36L9 66zM26 9h48L65 26H35z"/>'
        . '<path class="medal-shade" d="M74 9l17 57-41 33V80l26-20-11-34z"/>'
        . '<text x="50" y="72" text-anchor="middle" style="font-size:' . ($rank < 10 ? 62 : ($rank < 100 ? 48 : 36)) . 'px">' . $rank . '</text></svg>';
};
require __DIR__ . '/header.php';
?>
<div class="results-shell <?= $isHostResults ? 'results-host' : 'results-player' ?>" id="finalResults" data-host="<?= $isHostResults ? 'true' : 'false' ?>">
    <canvas class="results-confetti" id="resultsConfetti" aria-hidden="true"></canvas>
    <main class="results-main">
        <?php if ($myResult !== null): ?>
            <section class="results-personal" aria-label="Your final result">
                <div class="results-personal-main">
                    <?= $renderMedal($myRank) ?>
                    <h1 class="results-personal-rank"><?= $myRank ?><sup><?= $rankSuffix($myRank) ?></sup> place</h1>
                    <p class="results-personal-message"><?= $myRank === 1 ? 'You are the champion!' : ($myRank <= 3 ? 'You made the podium!' : 'Thanks for playing!') ?></p>
                    <?= player_appearance_image($resultCharacters[$myResult['id_player']], 'results-personal-character') ?>
                    <p class="results-personal-correct"><?= (int) $myResult['correct_count'] ?> out of <?= $questionCount ?> correct</p>
                    <p class="results-personal-total">Out of <?= $participantCount ?> <?= $participantCount === 1 ? 'player' : 'players' ?></p>
                    <div class="results-personal-actions">
                        <a href="#ceremonyStage" class="results-button results-button-light">View podium</a>
                        <a href="<?= BASE_URL ?>modules/player/join.php" class="results-button">Join another game</a>
                    </div>
                </div>
                <div class="results-personal-footer"><strong><?= htmlspecialchars($myResult['nickname']) ?></strong><span><?= number_format($myResult['score']) ?> <small>points</small></span></div>
            </section>
        <?php endif; ?>

        <section class="ceremony-stage" id="ceremonyStage" aria-label="Final podium">
            <div class="results-heading">
                <h1><?= htmlspecialchars($quiz['name'] ?? 'Game results') ?></h1>
                <p>Podium</p>
            </div>
            <div class="results-toolbar">
                <?php if ($isHostResults && $participantCount > 0): ?>
                    <button class="results-icon-button" id="resultsFullscreen" type="button" aria-label="Enter fullscreen" title="Fullscreen" hidden>&#x26F6;</button>
                    <button class="results-button" id="resultsMusic" type="button" aria-pressed="false" hidden>Music: Off</button>
                    <button class="results-button results-button-light" id="resultsSkip" type="button" hidden>Skip</button>
                <?php endif; ?>
            </div>
            <div class="results-spotlight" aria-hidden="true"><span></span></div>
        <?php if ($participantCount > 0): ?>
            <p class="results-sr-only" id="resultsAnnouncement" role="status" aria-live="polite">Congratulations to our champions!</p>
            <section class="results-podium" aria-label="Top three players">
                <?php foreach ([2, 1, 3] as $place):
                    $winner = $leaderboard[$place - 1] ?? null;
                ?>
                    <article class="results-winner results-place-<?= $place ?> <?= $winner ? '' : 'results-vacant' ?>" data-place="<?= $place ?>" data-nickname="<?= htmlspecialchars($winner['nickname'] ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Place <?= $place ?><?= $winner ? '' : ': no player' ?>">
                        <?php if ($winner): ?>
                        <div class="results-winner-identity">
                            <h2><?= htmlspecialchars($winner['nickname']) ?></h2>
                            <?= player_appearance_image($resultCharacters[$winner['id_player']], 'results-podium-character') ?>
                        </div>
                        <?php endif; ?>
                        <div class="results-plinth">
                            <?= $renderMedal($place) ?>
                            <?php if ($winner): ?>
                            <p class="results-winner-score"><strong><?= number_format($winner['score']) ?></strong> points</p>
                            <p class="results-winner-correct"><?= (int) $winner['correct_count'] ?> out of <?= $questionCount ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <section class="results-empty"><span aria-hidden="true">&#9734;</span><h2>No players this time</h2><p>Host another quiz and invite players to join the fun.</p></section>
        <?php endif; ?>
            <div class="results-stage-footer"><span class="results-brand">Kahootify!</span><span>Game PIN: <strong><?= htmlspecialchars($room['pin']) ?></strong></span></div>
        </section>

        <div class="results-afterparty" id="resultsAfterparty">
        <?php if ($participantCount > 0): ?>
            <?php if ($participantCount > 3): ?>
                <div class="results-runners" id="resultsRunners">
                    <span>A big hand for our runners-up</span>
                    <?php foreach (array_slice($leaderboard, 3, 2) as $index => $runner): ?>
                        <span class="results-runner"><strong><?= $index + 4 ?>th</strong> <?= player_appearance_image($resultCharacters[$runner['id_player']], 'results-runner-character') ?> <?= htmlspecialchars($runner['nickname']) ?> <small><?= number_format($runner['score']) ?> pts</small></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="results-controls">
                <?php if ($isHostResults): ?>
                    <button class="results-button" id="resultsReplay" type="button" hidden>Replay podium</button>
                <?php endif; ?>
                <button class="results-button results-button-light" id="resultsStandingsToggle" type="button" aria-expanded="true" aria-controls="resultsStandings" hidden>Hide full standings</button>
            </div>
        <?php endif; ?>

        <?php if ($participantCount > 0): ?>
            <section class="results-standings" id="resultsStandings" aria-labelledby="resultsStandingsTitle">
                <div class="results-standings-heading"><h2 id="resultsStandingsTitle">Final standings</h2><span><?= $participantCount ?> <?= $participantCount === 1 ? 'player' : 'players' ?></span></div>
                <div class="results-table-scroll">
                    <table class="results-table">
                        <caption class="results-sr-only">Players ranked by total points. Equal scores are ordered by joining time.</caption>
                        <thead><tr><th scope="col">Rank</th><th scope="col">Player</th><th scope="col">Correct</th><th scope="col">Points</th></tr></thead>
                        <tbody>
                            <?php foreach ($leaderboard as $index => $entry): $isMe = (int) $entry['id_player'] === $resultsPlayerId; ?>
                                <tr class="<?= $isMe ? 'results-my-row' : '' ?>">
                                    <td><span class="results-table-rank results-table-rank-<?= min($index + 1, 4) ?>"><?= $index + 1 ?></span></td>
                                    <th scope="row"><div class="results-player-name"><?= player_appearance_image($resultCharacters[$entry['id_player']], 'results-table-character') ?><span><?= htmlspecialchars($entry['nickname']) ?><?php if ($isMe): ?> <span class="results-you">YOU</span><?php endif; ?></span></div></th>
                                    <td><?= (int) $entry['correct_count'] ?><span class="results-question-total"> / <?= $questionCount ?></span></td>
                                    <td><?= number_format($entry['score']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="results-tie-note">Equal scores are ordered by joining time.</p>
            </section>
        <?php endif; ?>
        <footer class="results-footer"><a class="results-button" href="<?= BASE_URL ?><?= $isHostResults ? 'modules/auth/dashboard.php' : 'modules/player/join.php' ?>"><?= $isHostResults ? 'Back to dashboard' : 'Join another game' ?></a><p>Every answer is a step forward. Thanks for playing!</p></footer>
        </div>
    </main>
    <?php if ($isHostResults && $participantCount > 0): ?>
        <audio id="resultsAudio" preload="none" loop src="<?= BASE_URL ?>assets/audio/podium-soundtrack.mp3"></audio>
    <?php endif; ?>
</div>
<script src="<?= BASE_URL ?>assets/js/final-results.js" defer></script>
</body>
</html>
