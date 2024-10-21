const express = require('express');
const { exec } = require("child_process");
const bodyParser = require('body-parser');
require('dotenv').config()
const app = express();
const port = 3000;
const quote = require('shell-quote/quote');

function log(type, request, response) {
    console.log(`IP: ${request.header('x-forwarded-for')}`);
    console.log(`Request: ${type}`);
    console.log(`Response: ${response}`);
    console.log("");
};

function checkReq(req_ip, api_key) {
    return req_ip == '::ffff:10.0.0.40' && api_key === process.env.API_KEY;
}

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({extended: false}));

// app.get('/creds', (request, response) => {
//     let req_ip = request.socket.remoteAddress;

//     if (req_ip == '::ffff:10.0.0.40') {
//         response.send(process.env.ROOT_PASS);
//         log("GET /creds", request, "Allowed");
//     } else {
//         log(`GET /creds from ${req_ip}`, request, "Denied");
//     }
// });

app.post('/allow', (req, res) => {
    const addr = req.body.addr;
    const user = req.body.user;
    const api_key = req.body.api_key;
    const req_ip = req.socket.remoteAddress;


    if (checkReq(req_ip, api_key)) {

        const command = `echo "${process.env.ROOT_PASS}" | sudo -S ufw insert 1 allow from ${quote([addr])} proto tcp to any port 25565 comment ${quote([user])}`;
        exec(command, (error, stdout, stderr) => {
  if (error) {
    console.error(`exec error: ${error}`);
    return;
  }
  console.log(`stdout: ${stdout}`);
  console.error(`stderr: ${stderr}`);
});

        res.send("true");

        log(`lOST ${addr}, ${user} /allow`, req, `Added: ${quote([addr])}, ${quote([user])}`);
    } else {
        log(`POST ${addr}, ${user} /allow from ${req_ip}`, req, `Denied`);
    }
});

app.listen(port, () => console.log(`Server started on port: ${port}\n`));
