const express = require('express');
const { readFile } = require('fs');
const app = express();


app.get('/', (request, response) => {
    readFile('./src/html/nodeTest.html', 'utf8', (err, html) => {
        if (err) {
            console.log(err);
            response.status(500).send('server error\n');
        }

        response.send(html);
    });
});

app.get('/nodeSubfolder', (request, response) => {
    readFile('./src/html/nodeSubfolder.html', 'utf8', (err, html) => {
        if (err) {
            console.log(err);
            response.status(500).send('server error\n');
        }

        response.send(html);
    });
});

app.listen(3000, () => console.log("Server started on port 3000"));