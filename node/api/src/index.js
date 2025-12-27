const express = require('express');
const { readFile } = require('fs');
const bodyParser = require('body-parser');
require('dotenv').config()
const { randomBytes } = require('crypto');
const {Rcon} = require("rcon-client");
const quote = require('shell-quote/quote');
const mysql = require('mysql2/promise');
const app = express();
const port = 5000;

function log(type, request, response) {
    console.log(`IP: ${request.header('x-forwarded-for')}`);
    console.log(`Request: ${type}`);
    console.log(`Response: ${response}`);
    console.log("");
};

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({extended: false}));


app.get('/', (request, response) => {
    readFile('/var/www/node/api/src/docs.html', 'utf8', (err, html) => {
        if (err) {
            console.log(err);
            response.status(500).send('server error\n');
        }

        response.send(html);

        log("GET /", request, "Success");
    });
});

app.get('/status', async (req, res) => {
    try {
        const rcon = await Rcon.connect({
            host: "romaetplus.amdreier.com", port: 2570, password: process.env.RCON_PSWD
        });

        let response = await rcon.send(`list`);
        rcon.end();

        res.send(response)
    } catch (error) {
        // res.send({
        //     status: false,
        //     players: []
        // });
        res.send("Shutdown");
    }
});

app.post('/verifyDisc', async (req, res) => {
    try {
        const login_username = req.body.login_username;
        const disc_username = req.body.disc_username;
        const disc_uid = req.body.disc_uid;
        const token = req.body.token;
        const api_key = req.body.api_key;

        if (api_key != process.env.API_KEY) {
            console.log("bad API key for /verifyDisc");
            return;
        }

        const conn = await mysql.createConnection({
            host: process.env.DB_SERVER,
            user: process.env.DB_USER,
            database: process.env.DB_NAME,
            password: process.env.DB_PASS
        });

        // check if token exists and valid
        const checkTokenSQL = `
            SELECT dt.token, dt.disc_username, dt.expires
            FROM Discord_Tokens dt JOIN Users u ON dt.uid = u.uid
            WHERE u.username = ?
        `;
        const checkTokenParams = [login_username];

        const [rows] = await conn.execute(checkTokenSQL, checkTokenParams);

        if (rows.length == 0 || rows[0].token != token || rows[0].disc_username != disc_username) {
            res.status(401).send("No/incorrect token/discord username for this user")
            return;
        }

        const expiresAt = new Date(rows[0].expires);
        const now = new Date();

        if (expiresAt < now) {
            res.status(410).send("Token expired")
            return;
        }

        // Add discord info to user
        const addDiscSQL = `
            UPDATE Users
            SET disc_username = ?, disc_uid = ?
            WHERE username = ?
        `;
        const addDiscParams = [disc_username, disc_uid, login_username];

        await conn.execute(addDiscSQL, addDiscParams);

        // Delete token
        const deleteTokenSQL = `
            WITH t_uid AS (
                SELECT u.uid AS uid
                FROM Users u JOIN Discord_Tokens dt ON u.uid = dt.uid
                WHERE u.username = ?
            )
            DELETE From Discord_Tokens
            WHERE uid = (SELECT uid FROM t_uid)
        `;
        const deleteTokenParams = [login_username];

        await conn.execute(deleteTokenSQL, deleteTokenParams);

        console.log(`${login_username}: @${disc_username} verify successful`);
        res.status(200).send("Ok");
    } catch (error) {
        console.log(error);
        res.status(500).send("Server error");
    }
});

app.get('/verifyMC', async (req, res) => {
    try {
        const login_username = req.query.username;
        const token = req.query.token;

        const conn = await mysql.createConnection({
            host: process.env.DB_SERVER,
            user: process.env.DB_USER,
            database: process.env.DB_NAME,
            password: process.env.DB_PASS
        });

        // check if token exists and valid
        const checkTokenSQL = `
            SELECT mc.token, mc.mc_username, mc.expires
            FROM MC_Tokens mc JOIN Users u ON mc.uid = u.uid
            WHERE u.username = ?
        `;
        const checkTokenParams = [login_username];

        const [rows] = await conn.execute(checkTokenSQL, checkTokenParams);

        if (rows.length == 0 || rows[0].token != token) {
            res.status(401).send("No/incorrect token for this user")
            return;
        }

        const expiresAt = new Date(rows[0].expires);
        const now = new Date();

        if (expiresAt < now) {
            res.status(410).send("Token expired")
            return;
        }

        const mc_username = rows[0].mc_username;

        // Add discord info to user
        const addMCSQL = `
            UPDATE Users
            SET mc_username = ?
            WHERE username = ?
        `;
        const addMCParams = [mc_username, login_username];

        await conn.execute(addMCSQL, addMCParams);

        // Delete token
        const deleteTokenSQL = `
            WITH t_uid AS (
                SELECT u.uid AS uid
                FROM Users u JOIN MC_Tokens mc ON u.uid = mc.uid
                WHERE u.username = ?
            )
            DELETE From MC_Tokens
            WHERE uid = (SELECT uid FROM t_uid)
        `;
        const deleteTokenParams = [login_username];

        await conn.execute(deleteTokenSQL, deleteTokenParams);

        console.log(`${login_username}: mc:${mc_username} verify successful`);
        res.status(200).send(`
        <!DOCTYPE html>
        <html>
        <body style="font-family: sans-serif;">
        <h2>Success!</h2>
        <p>This window will close automatically...</p>
        <script>
            setTimeout(() => {
            window.close();
            }, 1000);
        </script>
        </body>
        </html>
        `);
    } catch (error) {
        console.log(error);
        res.status(500).send("Server error");
    }
});

app.post('/sendMCLink', async (req, res) => {
    try {
        const uid = req.body.uid;
        const login_username = req.body.login_username;
        const mc_username = req.body.mc_username;
        const api_key = req.body.api_key;

        if (api_key != process.env.API_KEY) {
            console.log("bad API key for /sendMCLink");
            return;
        }

        const mc_regex = /^\w+$/i;

        if (mc_username.length < 3 || mc_username.length > 16 || !mc_regex.test(mc_username)) {
            console.log("bad MC username for /sendMCLink")
            res.status(422).send("Invalid Minecraft username");
            return;
        }

        const conn = await mysql.createConnection({
            host: process.env.DB_SERVER,
            user: process.env.DB_USER,
            database: process.env.DB_NAME,
            password: process.env.DB_PASS
        });

        // create verify token
        const token = randomBytes(32).toString("base64");
        const insertTokenSQL = `
            INSERT INTO MC_Tokens (uid, token, mc_username, expires)
            VALUES (?, ?, ?, NOW() + INTERVAL 1 DAY)
            ON DUPLICATE KEY UPDATE
                token = VALUES(token),
                mc_username = VALUES(mc_username),
                expires = VALUES(expires)
        `;
        const insertTokenParams = [uid, token, mc_username];

        await conn.execute(insertTokenSQL, insertTokenParams);

        const rcon = await Rcon.connect({
            host: "romaetplus.amdreier.com", port: 2570, password: process.env.RCON_PSWD
        });

        const link = `https://romaetplus.amdreier.com/api/verifyMC?token=${encodeURIComponent(token)}&username=${encodeURIComponent(mc_username)}`;

        const command = `tellraw ${quote([mc_username])} {text:"Click here to verify",italic:true,underlined:true,color:"blue",click_event:{action:"open_url",url:"${link}"}}`;

        let response = await rcon.send(command);
        rcon.end();

        console.log(`${login_username} send MC link for ${mc_username}`);
        if (response == "No player was found") {
            res.status(404).send("No player with that username was found on the server. Please make sure the Minecraft username is correct, and you are currently on the server.")
        } else {
            res.status(200).send("Link sent! This linke will expire in 24 hours.");
        }
    } catch (error) {
        console.log(error);
        res.status(500).send("Server error");
    }
});

app.get('/resetLink', async (req, res) => {
    try {
        const login_username = req.query.login_username;
        const disc_uid = req.query.disc_uid;
        const api_key = req.query.api_key;

        if (api_key != process.env.API_KEY) {
            console.log("bad API key for /resetLink");
            return;
        }

        const conn = await mysql.createConnection({
            host: process.env.DB_SERVER,
            user: process.env.DB_USER,
            database: process.env.DB_NAME,
            password: process.env.DB_PASS
        });

        // check if token exists and valid
        const checkVerifiedSQL = `
            SELECT uid, disc_uid
            FROM Users
            WHERE username = ?
        `;
        const checkVerifiedParams = [login_username];

        const [rows] = await conn.execute(checkVerifiedSQL, checkVerifiedParams);

        if (rows.length == 0 || rows[0].disc_uid != disc_uid) {
            res.status(410).send("User's Discord not verified")
            return;
        }

        const uid = rows[0].uid;

        // Generate reset link
        const token = randomBytes(32).toString("base64");

        const addTokenSQL = `
            INSERT INTO Reset_Tokens (uid, token, expires)
            VALUES (?, ?, NOW() + INTERVAL 1 DAY)
            ON DUPLICATE KEY UPDATE
                token = VALUES(token),
                expires = VALUES(expires)
        `;
        const addTokenParams = [uid, token];

        try {
            const [result] = await conn.execute(addTokenSQL, addTokenParams);
        } catch (error) {
            console.log(`insert reset token err: ${error}`);
            res.status(500).send("Server Error");
            return;
        }

        const link = `https://romaetplus.amdreier.com/reset_pswd?token=${encodeURIComponent(token)}&username=${encodeURIComponent(login_username)}`;

        res.status(200).send(link)
        console.log(`Reset token created for ${login_username}`);
    } catch (error) {
        console.log(error);
        res.status(500).send("Server error");
    }
});

app.listen(port, () => console.log(`Server started on port: ${port}\n`));