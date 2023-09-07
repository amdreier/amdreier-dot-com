const express = require('express');
const { readFile } = require('fs');
const app = express();
const port = 3000;

function log(type, request, response) {
    console.log(`IP: ${request.header('x-forwarded-for')}`);
    console.log(`Request: ${type}`);
    console.log(`Response: ${response}`);
    console.log("");
};


app.get('/', (request, response) => {
    readFile('/var/www/node/src/html/nodeTest.html', 'utf8', (err, html) => {
        if (err) {
            console.log(err);
            response.status(500).send('server error\n');
        }

        response.send(html);

        log("GET /", request, "Success");
    });
});

app.get('/nodeSubfolder', (request, response) => {
    readFile('/var/www/node/src/html/nodeSubfolder.html', 'utf8', (err, html) => {
        if (err) {
            console.log(err);
            response.status(500).send('server error\n');
        }

        response.send(html);

        log("GET /nodeSubfolder", request, "Success");
    });
});

app.listen(port, () => console.log(`Server started on port: ${port}\n`));