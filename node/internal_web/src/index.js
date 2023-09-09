const express = require('express');
const { readFile } = require('fs');
const { exec } = require("child_process");
const bodyParser = require('body-parser');
require('dotenv').config()
const { createHash } = require('crypto');
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


function hash(input) {
    return createHash('sha256').update(input).digest('hex');
}

app.get('/pswd_hash', (req, res) => {
    let pswd_hash = req.query.pswd_hash;
    let password = req.query.password;
    let req_ip = req.socket.remoteAddress;


    if (pswd_hash === hash(password)) {
        res.send("true");
        log(`GET /pswd_hash`, req, 'TRUE');
    } else {
        res.send("false");
        log(`GET /pswd_hash`, req, 'FALSE');
    }
    
});

app.post('/pswd_hash', (req, res) => {
    let password = req.body.password;
    let req_ip = req.socket.remoteAddress;

    res.send(hash(password));
    log(`POST /pswd_hash`, req, 'COMPLETE');    
});


app.listen(port, () => console.log(`Server started on port: ${port}\n`));