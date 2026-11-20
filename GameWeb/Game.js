// Game state: creates racers, obstacle storage, and UI references used by gameplay.
const gameScene = document.querySelector("#game-scene");

const canvas = document.querySelector("#game-canvas");
const context = canvas.getContext("2d");
const players = [
	{ name: "Player 1", x: 0, y: 0, color: "#fc0404", up: "w", down: "s", direction: 0, score: 0, survivalRemainder: 0, hits: 0, hitTimer: 0, retreating: false, eliminated: false, survivalSeconds: null },
	{ name: "Player 2", x: 0, y: 0, color: "#55d6be", up: "arrowup", down: "arrowdown", direction: 0, score: 0, survivalRemainder: 0, hits: 0, hitTimer: 0, retreating: false, eliminated: false, survivalSeconds: null },
];
const heldKeys = new Set();
const obstacles = [];
const carWidth = 44;
const carHeight = 28;
const obstacleSize = 34;
const obstacleCarWidth = 44;
const obstacleCarHeight = 28;
const scoreDisplay = document.querySelector("#race-score");
const menuTitle = document.querySelector("#game-menu-title");
const menuMessage = document.querySelector("#game-menu-message");
const gameMenuCard = document.querySelector("#game-menu-card");
const startButton = document.querySelector("#start-game");
const leaderboardPanel = document.querySelector("#leaderboard-panel");
const leaderboardPanelTitle = document.querySelector("#leaderboard-panel-title");
const closeLeaderboardButton = document.querySelector("#close-leaderboard");
const loginForm = document.querySelector("#login-form");
const loginUsernameInput = document.querySelector("#login-username");
const loginPasswordInput = document.querySelector("#login-password");
const loginButton = document.querySelector("#login-button");
const loginView = document.querySelector("#login-view");
const playerSlotIndicator = document.querySelector("#player-slot-indicator");
const registerForm = document.querySelector("#register-form");
const registerUsernameInput = document.querySelector("#register-username");
const registerPasswordInput = document.querySelector("#register-password");
const registerConfirmPasswordInput = document.querySelector("#register-confirm-password");
const registerAdminOption = document.querySelector("#register-admin-option");
const registerAdminCheckbox = document.querySelector("#register-admin-checkbox");
const lobbyView = document.querySelector("#lobby-view");
const playerRoster = document.querySelector("#player-roster");
const logoutPlayersButton = document.querySelector("#logout-players");
const racerNameLabels = document.querySelectorAll(".player-name");
const playerPointsLabels = document.querySelectorAll(".player-points");
const leaderboardList = document.querySelector("#leaderboard-list");
const leaderboardPreviewList = document.querySelector("#leaderboard-preview-list");
const storageStatus = document.querySelector("#storage-status");
const raceTimerDisplay = document.querySelector("#race-timer");
const authEndpoint = new URL("auth.php", document.baseURI).toString();
const leaderboard = [];
const accounts = [];
const defaultPlayerNames = ["Player 1", "Player 2"];
let authenticatedPlayers = [];
let authenticationReady = false;
const adminRegistrationCode = "hesoyam";
let adminCodeProgress = 0;
let lastAdminCodeKeyAt = 0;
let adminCodeInputSnapshot = null;
let previousTime = 0;
let obstacleTimer = 0;
let passableObstacleTimer = 0;
let raceTime = 0;
let roadDashOffset = 0;

function renderLeaderboardList(listElement, entries = leaderboard, accountManagement = false) {
	listElement.replaceChildren();

	if (!entries.length) {
		const emptyMessage = document.createElement("li");
		emptyMessage.className = "leaderboard-empty";
		emptyMessage.textContent = accountManagement
			? "No accounts have been registered."
			: "No scores yet. Finish a race to join the leaderboard.";
		listElement.append(emptyMessage);
		return;
	}

	entries
		.slice()
		.forEach((entry, index) => {
			const item = document.createElement("li");
			item.className = "leaderboard-entry";

			const rank = document.createElement("span");
			rank.className = "leaderboard-rank";
			rank.textContent = String(index + 1).padStart(2, "0");

			const nameDisplay = document.createElement("span");
			nameDisplay.className = "leaderboard-name";
			nameDisplay.textContent = entry.name;

			const details = document.createElement("span");
			details.className = "entry-details";
			details.textContent = `${entry.score} pts`;

			item.append(rank, nameDisplay);
			item.append(details);
			if (accountManagement && authenticatedPlayers.some((player) => player.isAdmin)) {
				const actions = document.createElement("span");
				actions.className = "leaderboard-actions";
				[
					{ label: "Reset", operation: "reset" },
					{ label: "Delete", operation: "delete" },
				].forEach(({ label, operation }) => {
					const button = document.createElement("button");
					button.type = "button";
					button.className = "btn btn-outline-light";
					button.textContent = label;
					button.setAttribute("aria-label", `${label} ${entry.name}${operation === "delete" ? "'s account" : "'s score"}`);
					button.addEventListener("click", () => manageAccount(entry.name, operation, button));
					actions.append(button);
				});
				item.append(actions);
			}
			listElement.append(item);
		});
}

async function manageAccount(username, operation, button) {
	if (operation === "delete"
		&& !window.confirm(`Permanently delete ${username}'s account? This cannot be undone.`)) {
		return;
	}

	button.disabled = true;
	storageStatus.textContent = operation === "reset"
		? `Resetting ${username}'s score…`
		: `Deleting ${username}'s account…`;
	try {
		const result = await requestAuthentication("manage_account", { username, operation });
		if (Array.isArray(result.players)) {
			authenticatedPlayers = result.players;
			renderAuthenticationState();
			if (!authenticatedPlayers.some((player) => player.isAdmin)) {
				leaderboardPanel.hidden = true;
				gameMenuCard.hidden = false;
				loginUsernameInput.focus();
			}
		}
		await loadLeaderboard();
		if (authenticatedPlayers.some((player) => player.isAdmin)) {
			await loadAccounts();
		}
		storageStatus.textContent = result.message;
	} catch (error) {
		storageStatus.textContent = error.message;
	} finally {
		button.disabled = false;
	}
}

function renderLeaderboard() {
	renderLeaderboardList(leaderboardPreviewList);
	renderLeaderboardList(leaderboardList);
}

async function loadAccounts() {
	const response = await fetch(`${authEndpoint}?action=accounts`, {
		credentials: "same-origin",
	});
	let result;
	try {
		result = await response.json();
	} catch (error) {
		throw new Error("Account service returned an invalid response.");
	}
	if (!response.ok) {
		throw new Error(result.error || "Accounts could not be loaded.");
	}
	if (!Array.isArray(result.accounts)) {
		throw new Error("Account service returned invalid data.");
	}
	accounts.splice(0, accounts.length, ...result.accounts);
	renderLeaderboardList(leaderboardList, accounts, true);
}

async function loadLeaderboard() {
	const response = await fetch(`${authEndpoint}?action=leaderboard`, {
		credentials: "same-origin",
	});
	let result;
	try {
		result = await response.json();
	} catch (error) {
		throw new Error("Leaderboard service returned an invalid response.");
	}
	if (!response.ok) {
		throw new Error(result.error || "Leaderboard could not be loaded.");
	}
	if (!Array.isArray(result.leaderboard)) {
		throw new Error("Leaderboard service returned invalid data.");
	}
	leaderboard.splice(0, leaderboard.length, ...result.leaderboard);
	renderLeaderboard();
}

async function requestAuthentication(action, values = {}) {
	const options = action === "session"
		? { method: "GET", credentials: "same-origin" }
		: {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ action, ...values }),
		};
	const response = await fetch(authEndpoint, options);
	let result;
	try {
		result = await response.json();
	} catch (error) {
		throw new Error("Account service returned an invalid response.");
	}
	if (!response.ok) {
		const error = new Error(result.error || "Account request failed.");
		error.data = result;
		throw error;
	}
	return result;
}

function resetAdminRegistrationReveal() {
	adminCodeProgress = 0;
	lastAdminCodeKeyAt = 0;
	adminCodeInputSnapshot = null;
	registerAdminOption.hidden = true;
	registerAdminCheckbox.checked = false;
}

function getAdminCodeInputSnapshot(target) {
	if ((target instanceof HTMLInputElement && ["text", "password"].includes(target.type))
		|| target instanceof HTMLTextAreaElement) {
		return {
			target,
			value: target.value,
			selectionStart: target.selectionStart,
			selectionEnd: target.selectionEnd,
		};
	}
	return null;
}

function updateAdminRegistrationAccess() {
	if (registerForm.hidden) resetAdminRegistrationReveal();
}

function handleAdminRegistrationKeydown(event) {
	if (registerForm.hidden) {
		adminCodeProgress = 0;
		lastAdminCodeKeyAt = 0;
		adminCodeInputSnapshot = null;
		return;
	}

	const target = event.target;
	if (event.repeat) return;
	if (event.ctrlKey || event.altKey || event.metaKey
		|| (target instanceof Element && target.closest("select, [contenteditable='true']"))) {
		adminCodeProgress = 0;
		lastAdminCodeKeyAt = 0;
		adminCodeInputSnapshot = null;
		return;
	}

	const now = Date.now();
	if (now - lastAdminCodeKeyAt > 4000) {
		adminCodeProgress = 0;
		adminCodeInputSnapshot = null;
	}
	if (adminCodeInputSnapshot && adminCodeInputSnapshot.target !== target) {
		adminCodeProgress = 0;
		adminCodeInputSnapshot = null;
	}

	const key = event.key.toLowerCase();
	if (key === adminRegistrationCode[adminCodeProgress]) {
		if (adminCodeProgress === 0) adminCodeInputSnapshot = getAdminCodeInputSnapshot(target);
		adminCodeProgress += 1;
	} else {
		adminCodeProgress = key === adminRegistrationCode[0] ? 1 : 0;
		adminCodeInputSnapshot = adminCodeProgress ? getAdminCodeInputSnapshot(target) : null;
	}
	lastAdminCodeKeyAt = adminCodeProgress ? now : 0;

	if (adminCodeProgress === adminRegistrationCode.length) {
		event.preventDefault();
		if (adminCodeInputSnapshot && adminCodeInputSnapshot.target === target) {
			target.value = adminCodeInputSnapshot.value;
			target.setSelectionRange(
				adminCodeInputSnapshot.selectionStart,
				adminCodeInputSnapshot.selectionEnd
			);
		}
		registerAdminOption.hidden = false;
		registerAdminCheckbox.focus();
		adminCodeProgress = 0;
		lastAdminCodeKeyAt = 0;
		adminCodeInputSnapshot = null;
	}
}

function renderAuthenticationState() {
	const playerCount = Math.min(authenticatedPlayers.length, 2);
	loginView.hidden = playerCount >= 2;
	lobbyView.hidden = playerCount !== 2;
	playerSlotIndicator.textContent = `Player ${playerCount + 1}`;
	startButton.disabled = playerCount !== 2;

	players.forEach((player, index) => {
		player.name = authenticatedPlayers[index]?.username || defaultPlayerNames[index];
	});
	racerNameLabels.forEach((label, index) => { label.textContent = players[index].name; });
	updateScore();
	renderLeaderboard();
	updateAdminRegistrationAccess();

	if (playerCount === 1) {
		playerRoster.textContent = `Player 1 (Host): ${authenticatedPlayers[0].username} — log in with another account for Player 2.`;
	} else if (playerCount === 2) {
		playerRoster.textContent = `Player 1 (Host): ${authenticatedPlayers[0].username}  |  Player 2: ${authenticatedPlayers[1].username}`;
	} else {
		playerRoster.textContent = "";
	}
}

function openLeaderboardPanel() {
	const isAdministrator = authenticatedPlayers.some((player) => player.isAdmin);
	leaderboardPanelTitle.textContent = isAdministrator ? "Accounts" : "Leaderboard";
	gameMenuCard.hidden = true;
	leaderboardPanel.hidden = false;
	renderLeaderboard();
	if (isAdministrator) {
		loadAccounts().catch((error) => {
			storageStatus.textContent = error.message;
		});
	}
	closeLeaderboardButton.focus();
}

async function closeLeaderboardPanel() {
	if (authenticatedPlayers.some((player) => player.isAdmin)) {
		closeLeaderboardButton.disabled = true;
		storageStatus.textContent = "Logging out administrator…";
		try {
			const result = await requestAuthentication("logout");
			authenticatedPlayers = result.players;
			loginForm.reset();
			registerForm.reset();
			leaderboardPanel.hidden = true;
			gameMenuCard.hidden = false;
			renderAuthenticationState();
			storageStatus.textContent = "Administrator logged out";
			loginUsernameInput.focus();
		} catch (error) {
			storageStatus.textContent = `Administrator could not be logged out: ${error.message}`;
		} finally {
			closeLeaderboardButton.disabled = false;
		}
		return;
	}

	leaderboardPanel.hidden = true;
	gameMenuCard.hidden = false;
	startButton.focus();
}

async function saveRaceResults() {
	try {
		await requestAuthentication("save_scores", {
			scores: players.map((player) => player.score),
		});
	} catch (error) {
		storageStatus.textContent = `Race scores could not be saved: ${error.message}`;
		return;
	}
	try {
		await loadLeaderboard();
	} catch (error) {
		storageStatus.textContent = `Scores were saved, but the leaderboard could not be refreshed: ${error.message}`;
		return;
	}
	storageStatus.textContent = "Race scores saved to the leaderboard.";
}

// Draw immediately, then populate scores from the database.
renderLeaderboard();
loadLeaderboard().catch((error) => {
	storageStatus.textContent = error.message;
});

// Canvas and track rendering: sizes the drawing surface and lays out the road and racers.
function getRoadBounds() {
	const roadHeight = Math.min(320, Math.max(170, window.innerHeight * 0.68));
	return {
		top: (window.innerHeight - roadHeight) / 2,
		bottom: (window.innerHeight + roadHeight) / 2,
	};
}

function resizeCanvas() {
	const pixelRatio = window.devicePixelRatio || 1;
	canvas.width = Math.round(window.innerWidth * pixelRatio);
	canvas.height = Math.round(window.innerHeight * pixelRatio);
	context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
	if (players[0].x === 0 && players[1].x === 0) {
		players.forEach((player) => { player.x = window.innerWidth * 0.2; });
	}
	const road = getRoadBounds();
	players.forEach((player) => {
		player.y = Math.max(road.top + carHeight, Math.min(player.y || window.innerHeight / 2, road.bottom - carHeight));
	});
}

function drawPlayer(player, number) {
	if (player.hitTimer > 0 && Math.floor(player.hitTimer * 12) % 2 === 0) return;
	context.save();
	context.translate(player.x, player.y);
	context.shadowColor = player.color;
	context.shadowBlur = 18;
	context.beginPath();
	context.moveTo(-carWidth / 2 + 5, -carHeight / 2);
	context.lineTo(carWidth / 2 - 8, -carHeight / 2);
	context.lineTo(carWidth / 2, 0);
	context.lineTo(carWidth / 2 - 8, carHeight / 2);
	context.lineTo(-carWidth / 2 + 5, carHeight / 2);
	context.quadraticCurveTo(-carWidth / 2, carHeight / 2, -carWidth / 2, carHeight / 2 - 5);
	context.lineTo(-carWidth / 2, -carHeight / 2 + 5);
	context.quadraticCurveTo(-carWidth / 2, -carHeight / 2, -carWidth / 2 + 5, -carHeight / 2);
	context.closePath();
	context.fillStyle = player.color;
	context.fill();
	context.shadowBlur = 0;
	context.strokeStyle = "rgb(255 255 255 / 54%)";
	context.lineWidth = 1;
	context.stroke();
	context.fillStyle = "#07121d";
	context.fillRect(-carWidth / 2 + 7, -carHeight / 2 - 2, 8, 4);
	context.fillRect(-carWidth / 2 + 7, carHeight / 2 - 2, 8, 4);
	context.fillRect(carWidth / 2 - 15, -carHeight / 2 - 2, 8, 4);
	context.fillRect(carWidth / 2 - 15, carHeight / 2 - 2, 8, 4);
	context.fillStyle = "#162534";
	context.fillRect(2, -carHeight / 2 + 4, 11, carHeight - 8);
	context.fillStyle = "rgb(205 240 244 / 78%)";
	context.fillRect(4, -carHeight / 2 + 5, 6, carHeight - 10);
	context.fillStyle = "#ffe6a5";
	context.fillRect(carWidth / 2 - 4, -carHeight / 2 + 5, 3, 5);
	context.fillRect(carWidth / 2 - 4, carHeight / 2 - 10, 3, 5);
	context.fillStyle = "#fff";
	context.font = "bold 10px Trebuchet MS, sans-serif";
	context.textAlign = "center";
	context.textBaseline = "middle";
	context.fillText(String(number), -12, 0);
	context.restore();
}

function getObstacleSpeed(seconds, passable) {
	return !passable && seconds >= 95
		? Math.min(720 + (seconds - 95) * 4, 900)
		: Math.min(430 + seconds * 5, 720);
}

// Draws the shared road, including its animated center markings.
function drawRoad(time) {
	const { top, bottom } = getRoadBounds();
	const roadHeight = bottom - top;
	const roadGradient = context.createLinearGradient(0, top, 0, bottom);
	roadGradient.addColorStop(0, "rgb(18 34 43 / 94%)");
	roadGradient.addColorStop(0.5, "rgb(12 25 34 / 96%)");
	roadGradient.addColorStop(1, "rgb(18 34 43 / 94%)");
	context.fillStyle = roadGradient;
	context.fillRect(0, top, window.innerWidth, roadHeight);
	context.fillStyle = "rgb(3 10 17 / 48%)";
	context.fillRect(0, top - 12, window.innerWidth, 12);
	context.fillRect(0, bottom, window.innerWidth, 12);
	context.fillStyle = "rgb(93 224 203 / 34%)";
	context.fillRect(0, top, window.innerWidth, 2);
	context.fillRect(0, bottom - 2, window.innerWidth, 2);
	context.fillStyle = "rgb(246 189 75 / 72%)";
	context.fillRect(0, top + 5, window.innerWidth, 1);
	context.fillRect(0, bottom - 6, window.innerWidth, 1);
	context.strokeStyle = "rgb(202 228 231 / 48%)";
	context.lineWidth = 2;
	context.setLineDash([34, 28]);
	context.lineDashOffset = gameScene.dataset.state === "playing"
		? roadDashOffset
		: (time * 0.30) % 62;
	context.beginPath();
	context.moveTo(0, (top + bottom) / 2);
	context.lineTo(window.innerWidth, (top + bottom) / 2);
	context.stroke();
	context.setLineDash([]);

}

// Obstacle rendering: draws orange hazards as cars and keeps bonus pink boxes distinct.
function drawObstacle(obstacle) {
	if (obstacle.collected) return;
	if (obstacle.passable) {
		const obstacleColor = "#ff4fa3";
		context.fillStyle = obstacleColor;
		context.shadowColor = obstacleColor;
		context.shadowBlur = 12;
		context.fillRect(obstacle.x - obstacleSize / 2, obstacle.y - obstacleSize / 2, obstacleSize, obstacleSize);
		context.shadowBlur = 0;
		context.strokeStyle = "#ffd1e8";
		context.lineWidth = 2;
		context.strokeRect(obstacle.x - obstacleSize / 2 + 3, obstacle.y - obstacleSize / 2 + 3, obstacleSize - 6, obstacleSize - 6);
		context.fillStyle = "#ffd1e8";
		context.fillRect(obstacle.x - 9, obstacle.y - 5, 18, 7);
		context.fillStyle = obstacleColor;
		context.fillRect(obstacle.x - 6, obstacle.y - 4, 12, 5);
		return;
	}

	const obstacleColor = "#ff9100";
	const halfWidth = obstacleCarWidth / 2;
	const halfHeight = obstacleCarHeight / 2;
	context.save();
	context.translate(obstacle.x, obstacle.y);
	context.shadowColor = obstacleColor;
	context.shadowBlur = 12;
	context.fillStyle = obstacleColor;
	context.beginPath();
	context.moveTo(-halfWidth, 0);
	context.lineTo(-halfWidth + 7, -halfHeight + 3);
	context.quadraticCurveTo(-halfWidth + 10, -halfHeight, -halfWidth + 16, -halfHeight);
	context.lineTo(halfWidth - 7, -halfHeight);
	context.quadraticCurveTo(halfWidth, -halfHeight, halfWidth, -halfHeight + 6);
	context.lineTo(halfWidth, halfHeight - 6);
	context.quadraticCurveTo(halfWidth, halfHeight, halfWidth - 7, halfHeight);
	context.lineTo(-halfWidth + 16, halfHeight);
	context.quadraticCurveTo(-halfWidth + 10, halfHeight, -halfWidth + 7, halfHeight - 3);
	context.closePath();
	context.fill();
	context.shadowBlur = 0;
	context.strokeStyle = "#ffd1a1";
	context.lineWidth = 1.5;
	context.stroke();

	context.fillStyle = "#07121d";
	[-12, 12].forEach((wheelX) => {
		context.fillRect(wheelX - 5, -halfHeight - 1, 10, 4);
		context.fillRect(wheelX - 5, halfHeight - 3, 10, 4);
	});
	context.fillStyle = "#ffd7a3";
	context.beginPath();
	context.moveTo(-halfWidth + 8, -halfHeight + 4);
	context.lineTo(-halfWidth + 17, -halfHeight + 4);
	context.lineTo(-halfWidth + 22, -4);
	context.lineTo(-halfWidth + 8, -4);
	context.closePath();
	context.fill();
	context.beginPath();
	context.moveTo(-halfWidth + 8, halfHeight - 4);
	context.lineTo(-halfWidth + 17, halfHeight - 4);
	context.lineTo(-halfWidth + 22, 4);
	context.lineTo(-halfWidth + 8, 4);
	context.closePath();
	context.fill();
	context.fillStyle = "#ffe6a5";
	context.fillRect(-halfWidth + 1, -halfHeight + 4, 3, 5);
	context.fillRect(-halfWidth + 1, halfHeight - 9, 3, 5);
	context.fillStyle = "#ffcf74";
	context.fillRect(halfWidth - 4, -halfHeight + 4, 3, 5);
	context.fillRect(halfWidth - 4, halfHeight - 9, 3, 5);
	context.restore();
}

function getObstacleWidth(obstacle) {
	return obstacle.passable ? obstacleSize : obstacleCarWidth;
}

function getObstacleHeight(obstacle) {
	return obstacle.passable ? obstacleSize : obstacleCarHeight;
}

// Collision and score display helpers keep gameplay rules and HUD values in sync.
function overlaps(player, obstacle) {
	const horizontalRadius = (carWidth + getObstacleWidth(obstacle)) / 2;
	const verticalRadius = (carHeight + getObstacleHeight(obstacle)) / 2;
	const horizontalDistance = (player.x - obstacle.x) / horizontalRadius;
	const verticalDistance = (player.y - obstacle.y) / verticalRadius;
	return horizontalDistance ** 2 + verticalDistance ** 2 < 1;
}

function isCloseDodge(player, obstacle) {
	const verticalEdgeGap = Math.abs(player.y - obstacle.y)
		- (carHeight + getObstacleHeight(obstacle)) / 2;
	return verticalEdgeGap >= 0 && verticalEdgeGap <= 12;
}

function updateScore() {
	scoreDisplay.textContent = `${players[0].name}: ${players[0].score} pts (${players[0].hits} / 3 Hits) | ${players[1].name}: ${players[1].score} pts (${players[1].hits} / 3 Hits)`;
	playerPointsLabels.forEach((label, index) => {
		label.textContent = `Score: ${players[index].score} pts`;
		label.setAttribute("aria-label", `${players[index].name}: ${players[index].score} points`);
	});
}

function formatRaceTime(seconds) {
	const wholeSeconds = Math.floor(seconds);
	const minutes = Math.floor(wholeSeconds / 60);
	const remainingSeconds = wholeSeconds % 60;
	return `${String(minutes).padStart(2, "0")}:${String(remainingSeconds).padStart(2, "0")}`;
}

function startGame() {
	if (authenticatedPlayers.length !== 2) {
		storageStatus.textContent = "Both players must log in before starting the race.";
		return;
	}

	obstacles.length = 0;
	raceTime = 0;
	roadDashOffset = 0;
	obstacleTimer = 0;
	passableObstacleTimer = 0;
	heldKeys.clear();
	players.forEach((player, index) => {
		player.x = window.innerWidth * 0.2;
		player.y = window.innerHeight / 2 + (index === 0 ? -16 : 16);
		player.score = 0;
		player.survivalRemainder = 0;
		player.hits = 0;
		player.hitTimer = 0;
		player.direction = 0;
		player.retreating = false;
		player.eliminated = false;
		player.survivalSeconds = null;
	});
	updateScore();
	gameScene.dataset.state = "playing";
	raceTimerDisplay.textContent = "00:00";
	previousTime = performance.now();
}

function finishGame() {
	if (gameScene.dataset.state !== "playing") return;
	const [playerOne, playerTwo] = players;

	playerOne.survivalSeconds ??= raceTime;
	playerTwo.survivalSeconds ??= raceTime;

	const p1Time = playerOne.survivalSeconds;
	const p2Time = playerTwo.survivalSeconds;

	let winner = null;
	if (Math.abs(p1Time - p2Time) > 0.05) {
		winner = p1Time > p2Time ? playerOne : playerTwo;
	} else if (playerOne.score !== playerTwo.score) {
		winner = playerOne.score > playerTwo.score ? playerOne : playerTwo;
	}

	menuTitle.textContent = winner
		? `${winner.name} Wins!`
		: "It's a Draw!";

	menuMessage.textContent = `Final points — ${playerOne.name}: ${playerOne.score}, ${playerTwo.name}: ${playerTwo.score}.`;
	startButton.textContent = "Restart Race";
	gameScene.dataset.state = "gameover";
	heldKeys.clear();
	players.forEach((player) => { player.direction = 0; });
	saveRaceResults();
}

// Main game loop: updates movement, obstacles, collisions, scoring, and each rendered frame.
function animate(time) {
	const delta = Math.min((time - previousTime) / 1000 || 0, 0.05);
	previousTime = time;
	if (gameScene.dataset.state === "playing") {
		raceTime += delta;
		roadDashOffset = (roadDashOffset + getObstacleSpeed(raceTime, false) * delta) % 62;
		const road = getRoadBounds();
		const speed = 300;
		players.forEach((player) => {
			if (player.eliminated) return;
			player.hitTimer = Math.max(0, player.hitTimer - delta);
			if (!player.retreating) {
				player.survivalRemainder += delta;
				if (player.survivalRemainder >= 2) {
					const survivalPoints = Math.floor(player.survivalRemainder / 2);
					player.score += survivalPoints;
					player.survivalRemainder -= survivalPoints * 2;
					updateScore();
				}
			}
			if (player.retreating) {
				player.x -= 160 * delta;
				if (player.x <= -carWidth / 2) {
					player.eliminated = true;
					finishGame();
				}
				return;
			}
			const keyboardDirection = Number(heldKeys.has(player.down)) - Number(heldKeys.has(player.up));
			const direction = keyboardDirection || player.direction;
			player.y = Math.max(road.top + carHeight / 2, Math.min(player.y + direction * speed * delta, road.bottom - carHeight / 2));
		});

		if (gameScene.dataset.state === "playing") {
			obstacleTimer -= delta;
			if (obstacleTimer <= 0) {
				const y = road.top + obstacleSize + Math.random() * (road.bottom - road.top - obstacleSize * 2);
				obstacles.push({ x: window.innerWidth + obstacleSize, y, passable: false, hitPlayers: new Set(), nearMissPlayers: new Set() });
				obstacleTimer = raceTime >= 95
					? 0.35 + Math.random() * 0.3
					: 0.8 + Math.random() * 0.55;
			}

			if (raceTime >= 60) {
				passableObstacleTimer -= delta;
				if (passableObstacleTimer <= 0) {
					const y = road.top + obstacleSize + Math.random() * (road.bottom - road.top - obstacleSize * 2);
					obstacles.push({ x: window.innerWidth + obstacleSize, y, passable: true, hitPlayers: new Set(), nearMissPlayers: new Set() });
					passableObstacleTimer = 1.6 + Math.random() * 0.8;
				}
			}

			obstacles.forEach((obstacle) => {
				const obstacleSpeed = getObstacleSpeed(raceTime, obstacle.passable);
				const previousX = obstacle.x;
				obstacle.x -= obstacleSpeed * delta;
				players.forEach((player, index) => {
					if (player.retreating || player.eliminated) return;
					if (obstacle.passable) {
						if (!obstacle.collected && overlaps(player, obstacle)) {
							obstacle.collected = true;
							player.score += 3;
							updateScore();
						}
						return;
					}
					if (!obstacle.hitPlayers.has(index) && overlaps(player, obstacle)) {
						obstacle.hitPlayers.add(index);
						if (player.hitTimer <= 0) {
							player.score = Math.max(0, player.score - 3);
							player.hits += 1;
							player.hitTimer = 0.9;
							if (player.hits >= 3) {
								player.retreating = true;
								player.survivalSeconds = raceTime;
							}
							updateScore();
						}
					}
					if (!obstacle.nearMissPlayers.has(index)
						&& !obstacle.hitPlayers.has(index)
						&& previousX >= player.x
						&& obstacle.x < player.x
						&& isCloseDodge(player, obstacle)) {
						obstacle.nearMissPlayers.add(index);
						player.score += 5;
						updateScore();
					}
				});
			});
			for (let i = obstacles.length - 1; i >= 0; i--) {
				if (obstacles[i].collected || obstacles[i].x < -obstacleSize) {
					obstacles.splice(i, 1);
				}
			}
		}
	}

	context.clearRect(0, 0, window.innerWidth, window.innerHeight);
	drawRoad(time);
	if (gameScene.dataset.state === "playing") {
		raceTimerDisplay.textContent = formatRaceTime(raceTime);
	}
	obstacles.forEach(drawObstacle);
	players.forEach((player, index) => {
		if (!player.eliminated) drawPlayer(player, index + 1);
	});
	requestAnimationFrame(animate);
}

window.addEventListener("resize", resizeCanvas);
// Keyboard, pointer, and button handlers support both desktop and touch play.
window.addEventListener("keydown", (event) => {
	const key = event.key.toLowerCase();
	if (gameScene.dataset.state === "playing" && ["w", "s", "arrowup", "arrowdown"].includes(key)) {
		event.preventDefault();
		heldKeys.add(key);
	}
});
window.addEventListener("keyup", (event) => heldKeys.delete(event.key.toLowerCase()));
window.addEventListener("blur", () => heldKeys.clear());

document.querySelectorAll(".player-controls button").forEach((button) => {
	const player = players[Number(button.dataset.player)];
	const direction = Number(button.dataset.direction);
	button.addEventListener("pointerdown", (event) => {
		if (gameScene.dataset.state !== "playing" || player.retreating || player.eliminated) return;
		event.preventDefault();
		player.direction = direction;
		button.setPointerCapture(event.pointerId);
	});
	button.addEventListener("pointerup", () => { player.direction = 0; });
	button.addEventListener("pointercancel", () => { player.direction = 0; });
});

loginForm.addEventListener("submit", async (event) => {
	event.preventDefault();
	if (!authenticationReady) {
		storageStatus.textContent = "Preparing a fresh player session…";
		return;
	}
	const username = loginUsernameInput.value.trim();
	const password = loginPasswordInput.value;

	loginButton.disabled = true;
	storageStatus.textContent = "Logging in…";
	try {
		const result = await requestAuthentication("login", { username, password });
		authenticatedPlayers = result.players;
		loginUsernameInput.value = "";
		loginPasswordInput.value = "";
		renderAuthenticationState();
		storageStatus.textContent = authenticatedPlayers.some((player) => player.isAdmin)
			? "Administrator logged in."
			: authenticatedPlayers.length === 2
			? "Both players are ready. Start the race!"
			: "Player 1 is hosting. Log in with a different account for Player 2.";
		if (authenticatedPlayers.some((player) => player.isAdmin)) openLeaderboardPanel();
	} catch (error) {
		if (Array.isArray(error.data?.players)) {
			authenticatedPlayers = error.data.players;
			loginUsernameInput.value = "";
			loginPasswordInput.value = "";
			renderAuthenticationState();
		}
		storageStatus.textContent = error.message;
	} finally {
		loginButton.disabled = false;
	}
});

registerForm.addEventListener("submit", async (event) => {
	event.preventDefault();
	if (!authenticationReady) {
		storageStatus.textContent = "Preparing a fresh player session…";
		return;
	}
	const username = registerUsernameInput.value.trim();
	const password = registerPasswordInput.value;
	if (password !== registerConfirmPasswordInput.value) {
		storageStatus.textContent = "Passwords do not match.";
		return;
	}

	const submitButton = registerForm.querySelector('button[type="submit"]');
	submitButton.disabled = true;
	storageStatus.textContent = "Creating account…";
	try {
		const result = await requestAuthentication("register", {
			username,
			password,
			confirmPassword: registerConfirmPasswordInput.value,
			isAdmin: registerAdminCheckbox.checked,
		});
		authenticatedPlayers = result.players;
		registerForm.reset();
		registerForm.hidden = true;
		updateAdminRegistrationAccess();
		loginForm.reset();
		renderAuthenticationState();
		storageStatus.textContent = result.message;
		loginPasswordInput.focus();
	} catch (error) {
		storageStatus.textContent = error.message;
	} finally {
		submitButton.disabled = false;
	}
});

document.querySelector("#show-register").addEventListener("click", () => {
	loginView.hidden = true;
	registerForm.hidden = false;
	resetAdminRegistrationReveal();
	storageStatus.textContent = "";
	registerUsernameInput.value = loginUsernameInput.value.trim();
	registerUsernameInput.focus();
});

document.querySelector("#back-to-login").addEventListener("click", () => {
	registerForm.hidden = true;
	updateAdminRegistrationAccess();
	loginView.hidden = false;
	storageStatus.textContent = "";
	loginUsernameInput.focus();
});

window.addEventListener("keydown", handleAdminRegistrationKeydown);

logoutPlayersButton.addEventListener("click", async () => {
	logoutPlayersButton.disabled = true;
	storageStatus.textContent = "Logging out both players…";
	try {
		const result = await requestAuthentication("logout");
		authenticatedPlayers = result.players;
		loginForm.reset();
		registerForm.reset();
		renderAuthenticationState();
		storageStatus.textContent = "Both players are logged out. New players can sign in.";
	} catch (error) {
		storageStatus.textContent = error.message;
	} finally {
		logoutPlayersButton.disabled = false;
	}
});

startButton.addEventListener("click", () => {
	if (gameScene.dataset.state === "gameover") {
		startGame();
		return;
	}
	startGame();
});
closeLeaderboardButton.addEventListener("click", closeLeaderboardPanel);

resizeCanvas();
renderAuthenticationState();
requestAuthentication("logout")
	.then((result) => {
		authenticatedPlayers = Array.isArray(result.players) ? result.players : [];
		authenticationReady = true;
		renderAuthenticationState();
	})
	.catch((error) => {
		storageStatus.textContent = `Players could not be logged out: ${error.message}`;
	});
requestAnimationFrame(animate);