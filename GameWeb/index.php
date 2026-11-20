<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PASOPALABAS</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
	html,
	body {
		min-height: 100%;
	}

	body {
		margin: 0;
		overflow: hidden;
		background-image: linear-gradient(
			110deg,
			#101a2c 0%,
			#203c52 35%,
			#35635e 65%,
			#101a2c 100%
		);
		background-size: 300% 100%;
		animation: background-drift-left 24s linear infinite;
	}

	@keyframes background-drift-left {
		from { background-position: 0% 50%; }
		to { background-position: 100% 50%; }
	}

	@media (prefers-reduced-motion: reduce) {
		body {
			animation: none;
			background-position: 50% 50%;
		}
	}

	#game-scene {
		position: fixed;
		inset: 0;
		font-family: "Trebuchet MS", sans-serif;
		color: #f4f7f3;
		overflow: hidden;
	}

	#game-canvas {
		position: absolute;
		inset: 0;
		width: 100%;
		height: 100%;
	}

	#game-scene[data-state="menu"] #game-heading,
	#game-scene[data-state="menu"] .player-controls,
	#game-scene[data-state="menu"] #race-timer,
	#game-scene[data-state="gameover"] #game-heading,
	#game-scene[data-state="gameover"] .player-controls,
	#game-scene[data-state="gameover"] #race-timer {
		display: none;
	}

	#game-scene[data-state="playing"] #game-heading,
	#game-scene[data-state="playing"] .player-controls,
	#game-scene[data-state="playing"] #race-timer {
		display: block;
		z-index: 2;
	}

	#game-scene[data-state="playing"] .player-controls {
		display: flex;
	}

	#game-heading {
		position: absolute;
		top: 24px;
		left: 50%;
		transform: translateX(-50%);
		text-align: center;
		max-width: calc(100% - 24px);
		text-shadow: 0 2px 12px #101a2c;
	}

	#game-heading h1 {
		margin: 0;
		font-size: clamp(20px, 4vw, 30px);
	}

	#game-heading p {
		margin: 8px 0 0;
		color: #d4e3df;
		font-size: 14px;
	}

	#race-score {
		margin-top: 10px;
		font-size: 13px;
		font-weight: 700;
		color: #f4b942;
	}

	#race-scoring-rules {
		margin-top: 6px;
		padding: 6px 10px;
		border: 1px solid rgb(255 255 255 / 18%);
		border-radius: 8px;
		background: rgb(16 26 44 / 82%);
		color: #fff;
		font-size: 12px;
		line-height: 1.4;
	}

	.player-controls {
		position: absolute;
		bottom: max(24px, env(safe-area-inset-bottom));
		display: flex;
		gap: 10px;
		align-items: center;
		padding: 12px;
		border: 1px solid rgb(255 255 255 / 22%);
		border-radius: 12px;
		background: rgb(16 26 44 / 72%);
		backdrop-filter: blur(8px);
	}

	#player-one-controls { left: 24px; }
	#player-two-controls { right: 24px; }

	.player-points {
		color: #f4b942;
		font-size: 12px;
		font-weight: 700;
		white-space: nowrap;
	}

	#race-timer {
		position: absolute;
		top: max(24px, env(safe-area-inset-top));
		right: 24px;
		padding: 9px 16px;
		border: 1px solid rgb(255 255 255 / 22%);
		border-radius: 10px;
		background: rgb(16 26 44 / 82%);
		color: #fff;
		font-size: 20px;
		font-variant-numeric: tabular-nums;
		font-weight: 700;
		pointer-events: none;
	}

	.player-name {
		min-width: 72px;
		font-size: 13px;
		font-weight: 700;
	}

	.player-controls button {
		min-width: 58px;
		min-height: 44px;
		border: 1px solid rgb(255 255 255 / 30%);
		border-radius: 8px;
		background: #294c58;
		color: #fff;
		font: inherit;
		font-size: 12px;
		font-weight: 700;
		cursor: pointer;
		touch-action: none;
	}

	.player-controls button:active {
		background: #397668;
	}

	#game-overlay {
		position: absolute;
		inset: 0;
		display: grid;
		place-items: center;
		padding: 24px;
		background: rgb(8 16 25 / 48%);
	}

	#game-scene[data-state="playing"] #game-overlay {
		display: none;
	}

	#leaderboard-panel {
		position: absolute;
		inset: 0;
		z-index: 3;
		display: grid;
		place-items: center;
		padding: 24px;
		background: rgb(8 16 25 / 78%);
	}

	#leaderboard-panel[hidden] {
		display: none;
	}

	.leaderboard-panel-card {
		width: min(100%, 720px);
		max-height: calc(100dvh - 48px);
		overflow-y: auto;
		padding: 26px;
		border: 1px solid rgb(255 255 255 / 24%);
		border-radius: 18px;
		background: rgb(16 26 44 / 98%);
		box-shadow: 0 18px 55px rgb(0 0 0 / 38%);
	}

	.leaderboard-panel-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 12px;
	}

	.leaderboard-panel-header h2 {
		margin: 0;
		color: #f4b942;
		font-size: 20px;
	}

	.leaderboard-panel-card #leaderboard {
		margin-top: 0;
	}

	#game-menu-card {
		width: min(100%, 620px);
		max-height: calc(100dvh - 48px);
		overflow-y: auto;
		padding: 32px;
		border: 1px solid rgb(255 255 255 / 24%);
		border-radius: 18px;
		background: rgb(16 26 44 / 94%);
		box-shadow: 0 18px 55px rgb(0 0 0 / 38%);
		text-align: center;
		backdrop-filter: blur(12px);
	}

	#game-menu-card h1 {
		margin: 0;
		color: #f4b942;
		font-size: clamp(30px, 8vw, 48px);
	}

	#game-menu-card p {
		margin: 14px 0 24px;
		color: #d4e3df;
		line-height: 1.5;
	}

	.auth-actions {
		display: flex;
		flex-wrap: wrap;
		justify-content: center;
		gap: 10px;
	}

	#racer-name-form {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 10px;
		margin: 22px 0;
		text-align: left;
	}

	#racer-name-form label {
		display: grid;
		gap: 6px;
		color: #d4e3df;
		font-size: 13px;
		font-weight: 700;
	}

	#racer-name-form input {
		min-width: 0;
		padding: 9px 10px;
		border: 1px solid rgb(255 255 255 / 25%);
		border-radius: 7px;
		background: #162534;
		color: #fff;
		font: inherit;
	}

	#storage-status {
		grid-column: 1 / -1;
		min-height: 1em;
		margin: 0;
		color: #f4b942;
		font-size: 12px;
		text-align: center;
	}

	#leaderboard,
	#leaderboard-preview {
		margin-top: 24px;
		text-align: left;
	}

	#leaderboard h2,
	#leaderboard-preview h2 {
		margin: 0 0 10px;
		color: #f4b942;
		font-size: 19px;
		text-align: center;
	}

	#leaderboard-list,
	#leaderboard-preview-list {
		display: grid;
		gap: 8px;
		margin: 0;
		padding: 0;
		list-style: none;
	}

	.leaderboard-entry {
		display: grid;
		grid-template-columns: 1fr auto auto;
		gap: 8px 12px;
		align-items: center;
		padding: 10px;
		border: 1px solid rgb(255 255 255 / 16%);
		border-radius: 9px;
		background: rgb(41 76 88 / 55%);
	}

	.leaderboard-entry .entry-details {
		grid-column: 1 / -1;
		color: #d4e3df;
		font-size: 12px;
	}

	.leaderboard-empty {
		color: #d4e3df;
		font-size: 13px;
		text-align: center;
	}

	@media (max-width: 560px) {
		#game-heading { top: 18px; max-width: calc(100% - 140px); }
		#game-heading p { font-size: 12px; }
		#race-scoring-rules { max-width: 340px; font-size: 11px; }
		.player-controls {
			bottom: max(16px, env(safe-area-inset-bottom));
			gap: 6px;
			padding: 8px;
			width: calc(50% - 20px);
			box-sizing: border-box;
			flex-wrap: wrap;
			justify-content: space-between;
		}
		#player-one-controls { left: 10px; }
		#player-two-controls { right: 10px; }
		.player-name { min-width: 0; font-size: 11px; }
		.player-points { font-size: 10px; }
		.player-controls button { flex: 1; min-width: 0; min-height: 42px; padding: 0 3px; font-size: 9px; }
		#race-timer {
			top: max(16px, env(safe-area-inset-top));
			right: 12px;
			padding: 7px 11px;
			font-size: 17px;
		}
		#game-menu-card { padding: 26px 20px; }
		.leaderboard-entry {
			grid-template-columns: 1fr auto;
		}
	}

	body {
		animation: none;
		background:
			radial-gradient(ellipse at 18% 18%, rgb(25 91 103 / 28%), transparent 38%),
			radial-gradient(ellipse at 82% 86%, rgb(22 85 76 / 24%), transparent 34%),
			#07121d;
		background-size: auto;
	}

	#game-scene::before,
	#game-scene::after {
		position: absolute;
		inset: 0;
		z-index: 1;
		content: "";
		pointer-events: none;
	}

	#game-scene::before {
		background:
			radial-gradient(ellipse at 50% 50%, transparent 12%, rgb(3 10 17 / 48%) 100%),
			linear-gradient(180deg, rgb(5 13 22 / 22%), transparent 30%, transparent 72%, rgb(5 13 22 / 38%));
	}

	#game-scene::after {
		opacity: 0.14;
		background-image:
			linear-gradient(rgb(169 216 218 / 12%) 1px, transparent 1px),
			linear-gradient(90deg, rgb(169 216 218 / 12%) 1px, transparent 1px);
		background-size: 48px 48px;
		mask-image: linear-gradient(transparent, #000 18%, #000 82%, transparent);
	}

	#game-canvas { z-index: 0; }

	#game-heading {
		top: max(20px, env(safe-area-inset-top));
		padding: 14px 20px;
		border: 1px solid rgb(180 221 227 / 15%);
		border-radius: 16px;
		background: rgb(7 18 29 / 78%);
		box-shadow: 0 12px 36px rgb(0 0 0 / 24%);
		backdrop-filter: blur(14px);
	}

	#game-heading h1 {
		color: #f6bd4b;
		font-size: clamp(19px, 3.2vw, 26px);
		letter-spacing: 0.08em;
	}

	#game-heading p { color: #b6cbd0; font-size: 12px; }

	#race-score {
		color: #e9f4f5;
		font-size: 12px;
		font-variant-numeric: tabular-nums;
	}

	#race-scoring-rules {
		border: 0;
		background: transparent;
		color: #9fb6bf;
		font-size: 10px;
	}

	.player-controls {
		z-index: 2;
		gap: 9px;
		padding: 10px 12px;
		border: 1px solid rgb(180 221 227 / 18%);
		border-radius: 14px;
		background: rgb(7 18 29 / 84%);
		box-shadow: 0 12px 32px rgb(0 0 0 / 24%);
		backdrop-filter: blur(14px);
	}

	.player-points { color: #f6bd4b; }

	#race-timer {
		z-index: 2;
		border-color: rgb(246 189 75 / 36%);
		border-radius: 12px;
		background: rgb(7 18 29 / 88%);
		color: #f6bd4b;
		box-shadow: 0 10px 28px rgb(0 0 0 / 22%);
	}

	#player-one-controls .player-name::before,
	#player-two-controls .player-name::before {
		display: inline-block;
		width: 8px;
		height: 8px;
		margin-right: 7px;
		border-radius: 50%;
		content: "";
		vertical-align: 1px;
	}

	#player-one-controls .player-name::before {
		background: #ff6868;
		box-shadow: 0 0 12px rgb(255 104 104 / 70%);
	}

	#player-two-controls .player-name::before {
		background: #5de0cb;
		box-shadow: 0 0 12px rgb(93 224 203 / 70%);
	}

	.player-controls button {
		min-height: 40px;
		border-color: rgb(180 221 227 / 20%);
		border-radius: 9px;
		background: #132d3b;
		transition: background 140ms ease, border-color 140ms ease, transform 140ms ease;
	}

	.player-controls button:hover {
		transform: translateY(-1px);
		border-color: rgb(93 224 203 / 56%);
		background: #1b4652;
	}

	.player-controls button:active {
		transform: translateY(1px);
		background: #236357;
	}

	#game-overlay {
		z-index: 3;
		padding: clamp(12px, 3vw, 30px);
		background:
			radial-gradient(ellipse at 50% 18%, rgb(22 89 92 / 20%), transparent 55%),
			rgb(3 10 17 / 58%);
	}

	#game-menu-card {
		position: relative;
		width: min(100%, 840px);
		max-height: calc(100dvh - 40px);
		box-sizing: border-box;
		padding: clamp(22px, 4vw, 38px);
		border: 1px solid rgb(180 221 227 / 22%);
		border-radius: 24px;
		background:
			radial-gradient(ellipse at 88% 0%, rgb(42 111 111 / 20%), transparent 36%),
			linear-gradient(145deg, rgb(13 31 44 / 98%), rgb(7 19 31 / 98%));
		box-shadow: 0 28px 90px rgb(0 0 0 / 52%), inset 0 1px rgb(255 255 255 / 5%);
		text-align: left;
		backdrop-filter: blur(20px);
		scrollbar-color: #315564 transparent;
		scrollbar-width: thin;
	}

	#game-menu-card::before {
		position: absolute;
		top: 0;
		right: 14%;
		left: 14%;
		height: 2px;
		border-radius: 0 0 4px 4px;
		background: linear-gradient(90deg, transparent, #5de0cb, #f6bd4b, transparent);
		content: "";
	}

	.game-kicker {
		margin-bottom: 12px;
		color: #7de4d2;
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.2em;
		text-transform: uppercase;
	}

	#game-menu-card h1 {
		color: #f6bd4b;
		font-size: clamp(34px, 7vw, 54px);
		line-height: 1;
		letter-spacing: -0.055em;
		text-shadow: 0 6px 30px rgb(246 189 75 / 15%);
	}

	#game-menu-card #game-menu-title,
	#game-heading h1 {
		font-family: Impact, "Arial Black", sans-serif;
		font-weight: 900;
		letter-spacing: 0.1em;
	}

	#game-menu-card #game-menu-title {
		width: 100%;
		text-align: left;
		text-shadow: 0 3px 0 rgb(95 59 20 / 40%), 0 0 28px rgb(246 189 75 / 32%);
	}

	.game-kicker {
		margin-bottom: 12px;
		color: #7de4d2;
		font-size: 10px;
		font-weight: 700;
		letter-spacing: 0.2em;
		text-transform: uppercase;
	}

	#game-menu-card p {
		margin: 12px 0 20px;
		color: #afc5cb;
		font-size: 14px;
		line-height: 1.65;
	}

	.auth-actions {
		justify-content: flex-start;
		gap: 9px;
		margin: 20px 0 24px;
	}

	.auth-actions button:focus-visible,
	.leaderboard-entry button:focus-visible,
	.player-controls button:focus-visible {
		outline: 3px solid #7de4d2;
		outline-offset: 3px;
	}
	.leaderboard-actions .btn { white-space: nowrap; }
	h1, h2 { font-weight: 700; }

	#racer-name-form {
		gap: 12px;
		margin: 0;
		padding: 18px;
		border: 1px solid rgb(180 221 227 / 12%);
		border-radius: 15px;
		background: rgb(3 13 22 / 42%);
	}

	#racer-name-form label {
		gap: 8px;
		color: #b9cdd2;
		font-size: 11px;
		letter-spacing: 0.06em;
		text-transform: uppercase;
	}

	#racer-name-form input {
		padding: 10px 12px;
		box-sizing: border-box;
		border-color: rgb(180 221 227 / 20%);
		border-radius: 9px;
		background: #0c202c;
		color: #f3f8fa;
		transition: border-color 140ms ease, box-shadow 140ms ease;
	}

	#racer-name-form input:focus {
		border-color: #5de0cb;
		outline: none;
		box-shadow: 0 0 0 3px rgb(93 224 203 / 12%);
	}

	#player-slot-indicator {
		margin: 20px 0 7px;
		color: #7de4d2;
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.12em;
		text-transform: uppercase;
	}

	#login-form,
	#register-form {
		margin: 0;
	}

	#register-form[hidden],
	#login-view[hidden],
	#lobby-view[hidden] {
		display: none;
	}

	#register-form .registration-fields {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 12px;
		margin: 0 0 14px;
		padding: 18px;
		border: 1px solid rgb(180 221 227 / 12%);
		border-radius: 15px;
		background: rgb(3 13 22 / 42%);
		text-align: left;
	}

	#register-form label {
		display: grid;
		gap: 8px;
		color: #b9cdd2;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.06em;
		text-transform: uppercase;
	}

	#register-form #register-admin-option {
		display: flex;
		align-items: center;
		gap: 9px;
		color: #f4b942;
		cursor: pointer;
	}

	#register-form #register-admin-option[hidden] {
		display: none;
	}

	#register-form #register-admin-option input {
		width: auto;
		margin: 0;
		accent-color: #f4b942;
	}

	#register-form input {
		width: 100%;
		min-width: 0;
		padding: 10px 12px;
		box-sizing: border-box;
		border: 1px solid rgb(180 221 227 / 20%);
		border-radius: 9px;
		background: #0c202c;
		color: #f3f8fa;
		font: inherit;
		text-transform: none;
	}

	#register-form input:focus {
		border-color: #5de0cb;
		outline: none;
		box-shadow: 0 0 0 3px rgb(93 224 203 / 12%);
	}

	#lobby-view {
		margin-top: 20px;
		padding: 18px;
		border: 1px solid rgb(180 221 227 / 12%);
		border-radius: 15px;
		background: rgb(3 13 22 / 42%);
	}

	#player-roster {
		margin: 0 0 14px;
		color: #d4e5e9;
	}

	.auth-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
	}

	#game-menu-card #storage-status {
		min-height: 1.2em;
		margin: 8px 0 0;
		color: #f6bd4b;
		font-size: 12px;
		line-height: 1.2;
		text-align: center;
	}

	#register-form .auth-actions {
		margin-bottom: 8px;
	}

	#leaderboard,
	#leaderboard-preview {
		margin-top: 26px;
	}

	.leaderboard-heading {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		gap: 12px;
		margin-bottom: 12px;
	}

	#leaderboard h2,
	#leaderboard-preview h2 {
		margin: 0;
		color: #f3f8fa;
		font-size: 18px;
		letter-spacing: -0.02em;
	}

	.leaderboard-subtitle {
		margin: 0;
		color: #7999a2;
		font-size: 10px;
		line-height: 1.2;
		letter-spacing: 0.1em;
		text-transform: uppercase;
	}

	#leaderboard-list,
	#leaderboard-preview-list { gap: 7px; }

	.leaderboard-entry {
		grid-template-columns: 38px minmax(0, 1fr) auto;
		gap: 8px 11px;
		padding: 10px 12px;
		border: 1px solid rgb(180 221 227 / 11%);
		border-radius: 12px;
		background: linear-gradient(100deg, rgb(20 49 61 / 82%), rgb(13 34 47 / 72%));
		transition: transform 140ms ease, border-color 140ms ease, background 140ms ease;
	}

	.leaderboard-entry:hover {
		transform: translateX(2px);
		border-color: rgb(125 228 210 / 30%);
		background: linear-gradient(100deg, rgb(24 59 69 / 92%), rgb(15 41 53 / 86%));
	}

	.leaderboard-rank {
		display: grid;
		width: 30px;
		height: 30px;
		place-items: center;
		border-radius: 9px;
		background: rgb(147 179 188 / 10%);
		color: #9fb6bf;
		font-size: 12px;
		font-weight: 700;
		font-variant-numeric: tabular-nums;
	}

	.leaderboard-entry:nth-child(1) .leaderboard-rank {
		background: rgb(246 189 75 / 16%);
		color: #ffd273;
	}

	.leaderboard-entry:nth-child(2) .leaderboard-rank {
		background: rgb(191 211 217 / 12%);
		color: #d4e5e9;
	}

	.leaderboard-entry:nth-child(3) .leaderboard-rank {
		background: rgb(205 129 84 / 14%);
		color: #e5ad87;
	}

	.leaderboard-entry .leaderboard-name {
		overflow-wrap: anywhere;
	}

	.leaderboard-actions {
		display: flex;
		gap: 6px;
	}

	.leaderboard-entry .entry-details {
		grid-column: 2 / -1;
		color: #91aab2;
		font-size: 11px;
		line-height: 1.45;
	}


	.leaderboard-empty {
		padding: 20px 14px;
		border: 1px dashed rgb(180 221 227 / 18%);
		border-radius: 12px;
		color: #91aab2;
		font-size: 12px;
		line-height: 1.5;
	}

	@media (prefers-reduced-motion: reduce) {
		*, *::before, *::after { transition-duration: 0.01ms !important; }
	}

	@media (max-width: 560px) {
		#game-heading {
			top: max(12px, env(safe-area-inset-top));
			max-width: calc(100% - 118px);
			padding: 10px 12px;
			border-radius: 12px;
		}
		#game-heading h1 { font-size: 16px; }
		#game-heading p { font-size: 10px; }
		#race-score { margin-top: 7px; font-size: 10px; }
		#race-scoring-rules { max-width: 300px; margin-top: 5px; font-size: 9px; }
		.player-controls {
			bottom: max(12px, env(safe-area-inset-bottom));
			gap: 5px;
			padding: 8px;
			width: calc(50% - 16px);
			border-radius: 11px;
		}
		#player-one-controls { left: 8px; }
		#player-two-controls { right: 8px; }
		.player-name { min-width: 0; font-size: 10px; }
		.player-points { font-size: 9px; }
		.player-controls button { flex: 1; min-width: 0; min-height: 38px; padding: 0 2px; font-size: 9px; }
		#race-timer {
			top: max(12px, env(safe-area-inset-top));
			right: 10px;
			padding: 8px 10px;
			font-size: 16px;
		}
		#game-menu-card {
			max-height: calc(100dvh - 24px);
			padding: 24px 18px;
			border-radius: 19px;
		}
		#leaderboard-panel { padding: 12px; }
		.leaderboard-panel-card {
			max-height: calc(100dvh - 24px);
			padding: 18px 12px;
		}
		#game-menu-card h1 { font-size: clamp(32px, 10vw, 44px); }
		#game-menu-card p { font-size: 13px; }
		#racer-name-form { gap: 9px; padding: 13px; }
		#racer-name-form label,
		#register-form label { font-size: 9px; }
		#racer-name-form input,
		#register-form input { padding: 9px; font-size: 12px; }
		#register-form .registration-fields { gap: 9px; padding: 13px; }
		#lobby-view { padding: 13px; }
		#leaderboard,
		#leaderboard-preview { margin-top: 21px; }
		.leaderboard-heading { align-items: flex-start; flex-direction: column; gap: 5px; }
		.leaderboard-entry {
			grid-template-columns: 30px minmax(0, 1fr) auto;
			gap: 7px;
			padding: 9px;
		}
		.leaderboard-rank { width: 26px; height: 26px; }
		.leaderboard-actions { gap: 4px; }
		.leaderboard-entry button { min-height: 30px; padding: 0 7px; font-size: 10px; }
		.leaderboard-entry .entry-details { grid-column: 2 / -1; font-size: 10px; }
	}

	@media (max-height: 620px) and (min-width: 561px) {
		#game-menu-card { padding: 20px 28px; }
		.game-kicker { margin-bottom: 8px; }
		#game-menu-card h1 { font-size: 38px; }
		#game-menu-card p { margin: 8px 0 12px; }
		.auth-actions { margin: 12px 0 15px; }
		#leaderboard,
		#leaderboard-preview { margin-top: 17px; }
	}

	@media (max-width: 360px) {
		#racer-name-form,
		#register-form .registration-fields { grid-template-columns: 1fr; }
		.auth-actions button { min-width: 100%; }
		#race-scoring-rules { display: none; }
	}
    </style>
</head>
<body>
    <main id="game-scene" data-state="menu" aria-label="Two-player racing game">
        <canvas id="game-canvas" aria-label="Dodge incoming cars and bonus boxes on a shared race track"></canvas>
        <header id="game-heading">
            <h1>PASOPALABAS</h1>
            <p>Dodge orange cars; collect pink bonus boxes</p>
            <div id="race-score" aria-live="polite">P1: 0 pts | P2: 0 pts</div>
            <div id="race-scoring-rules">Orange car dodged: +2 pts &nbsp;|&nbsp; Every 2 sec: +1 pt &nbsp;|&nbsp; Pink box collected: +3 pts</div>
        </header>
        <section class="player-controls" id="player-one-controls" aria-label="Player one keyboard and touch controls">
            <span class="player-name">Player 1</span>
            <span class="player-points" aria-label="Player one score">Score: 0 pts</span>
            <button type="button" data-player="0" data-direction="-1" aria-label="Move up with W">W / UP</button>
            <button type="button" data-player="0" data-direction="1" aria-label="Move down with S">S / DOWN</button>
        </section>
        <section class="player-controls" id="player-two-controls" aria-label="Player two keyboard and touch controls">
            <span class="player-name">Player 2</span>
            <span class="player-points" aria-label="Player two score">Score: 0 pts</span>
            <button type="button" data-player="1" data-direction="-1" aria-label="Move up with the Up Arrow">↑ / UP</button>
            <button type="button" data-player="1" data-direction="1" aria-label="Move down with the Down Arrow">↓ / DOWN</button>
        </section>
        <output id="race-timer" role="timer" aria-label="Race time" aria-live="off">00:00</output>
        <section id="game-overlay" aria-live="polite">
            <div id="game-menu-card">
                <div class="game-kicker">Two-player arcade survival</div>
                <h1 id="game-menu-title">PASOPALABAS</h1>
                <p id="game-menu-message">Player 1: W / S &nbsp; | &nbsp; Player 2: ↑ / ↓</p>
                <div id="login-view">
                    <div id="player-slot-indicator" aria-live="polite">Player 1</div>
                    <form id="login-form">
                        <div id="racer-name-form">
                            <label for="login-username">Username
                                <input id="login-username" name="username" maxlength="24" autocomplete="username" required>
                            </label>
                            <label for="login-password">Password
                                <input id="login-password" name="password" type="password" autocomplete="current-password" required>
                            </label>
                        </div>
                        <div class="auth-actions">
                            <button type="submit" class="btn btn-warning btn-lg" id="login-button">Log In</button>
                            <button type="button" class="btn btn-outline-info btn-lg" id="show-register">Register</button>
                        </div>
                    </form>
                </div>
                <form id="register-form" hidden>
                    <div class="game-kicker">Create your account</div>
                    <div class="registration-fields">
                        <label for="register-username">Username
                            <input id="register-username" name="username" maxlength="24" autocomplete="username" required>
                        </label>
                        <label for="register-password">Password
                            <input id="register-password" name="password" type="password" autocomplete="new-password" required>
                        </label>
                        <label for="register-confirm-password">Confirm password
                            <input id="register-confirm-password" name="confirm-password" type="password" autocomplete="new-password" required>
                        </label>
                        <label id="register-admin-option" hidden>
                            <input id="register-admin-checkbox" name="is-admin" type="checkbox">
                            Create as administrator
                        </label>
                    </div>
                    <div class="auth-actions">
                        <button type="submit" class="btn btn-warning btn-lg">Make Account</button>
                        <button type="button" class="btn btn-outline-light btn-lg" id="back-to-login">Back to Login</button>
                    </div>
                </form>
                <section id="lobby-view" hidden aria-live="polite">
                    <p id="player-roster"></p>
                    <div class="auth-actions">
                        <button type="button" class="btn btn-warning btn-lg" id="start-game" disabled>Start Game</button>
                        <button type="button" class="btn btn-outline-info" id="logout-players" aria-label="Log out both players">Log Out</button>
                    </div>
                </section>
                <p id="storage-status" role="status" aria-live="polite"></p>
                <section id="leaderboard-preview" aria-labelledby="leaderboard-preview-title">
                    <div class="leaderboard-heading">
                        <h2 id="leaderboard-preview-title">Leaderboard</h2>
                        <p class="leaderboard-subtitle">Highest score takes the crown</p>
                    </div>
                    <ol id="leaderboard-preview-list"></ol>
                </section>
            </div>
            <section id="leaderboard-panel" hidden role="dialog" aria-modal="true" aria-labelledby="leaderboard-panel-title">
                <div class="leaderboard-panel-card">
                    <div class="leaderboard-panel-header">
                        <h2 id="leaderboard-panel-title">Leaderboard</h2>
                        <button type="button" class="btn btn-outline-light" id="close-leaderboard">Back</button>
                    </div>
                    <section id="leaderboard" aria-labelledby="leaderboard-title">
                        <div class="leaderboard-heading">
                            <p class="leaderboard-subtitle">Highest score takes the crown</p>
                        </div>
                        <ol id="leaderboard-list"></ol>
                    </section>
                </div>
            </section>
        </section>
    </main>
    <script src="Game.js?v=20261119-18"></script>
</body>
</html>