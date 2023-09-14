const express = require('express');
const { readFile } = require('fs');
const { exec } = require("child_process");
const bodyParser = require('body-parser');
require('dotenv').config()
const { scryptSync, randomBytes, timingSafeEqual } = require('crypto');
const { create } = require('domain');
const app = express();
const port = 4000;

function log(type, request, response) {
    console.log(`IP: ${request.header('x-forwarded-for')}`);
    console.log(`Request: ${type}`);
    console.log(`Response: ${response}`);
    console.log("");
};

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({extended: false}));


function signUp(password) {
    const password_pep = `${password}+${process.env.PEPPER}`;

    const salt = randomBytes(32).toString('hex');
    const pswd_hash = scryptSync(password_pep, salt, 64);

    return `${salt}:${pswd_hash.toString('hex')}`;
}

function login(password, pswd_hash) {
    const password_pep = `${password}+${process.env.PEPPER}`;

    const [salt, hash] = pswd_hash.split(':');
    const hashed_password = scryptSync(password_pep, salt, 64);

    const hashBuffer = Buffer.from(hash, 'hex');

    return timingSafeEqual(hashed_password, hashBuffer);
}

app.get('/pswd_hash', (req, res) => {
    const pswd_hash = req.query.pswd_hash;
    const password = req.query.password;
    const int_key = req.query.int_key;
    const req_ip = req.socket.remoteAddress;

    if (int_key == process.env.INT_KEY && req_ip == "::ffff:127.0.0.1") {
        if (login(password, pswd_hash)) {
            res.send("true");
            log('GET /pswd_hash', req, 'TRUE');
        } else {
            res.send("false");
            log('GET /pswd_hash', req, 'FALSE');
        }
    }
});

app.post('/pswd_hash', (req, res) => {
    const password = req.body.password;
    const int_key = req.body.int_key;
    const req_ip = req.socket.remoteAddress;

    if (int_key == process.env.INT_KEY && req_ip == "::ffff:127.0.0.1") {
        res.send(signUp(password));
        log('POST /pswd_hash', req, 'COMPLETE');    
    }
});


app.listen(port, () => console.log(`Server started on port: ${port}\n`));