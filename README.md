# This is the GitHub Repo for amdreier.com and romaetplus.amdreier.com
## The 'main' folder corresponds with the root directory of amdreier.com
## The 'rep' folder corresponds with the root directory of romaetplus.amdreier.com
## The 'node' folder contains the different node.js servers which respond to internal requests on different servers
## The 'shared' folder is content which is shared between all folders
---

# For PennSpark:
[amdreier.com](https://amdreier.com) is my online portfolio, which I self host (check out the [amdreier.com project page](https://amdreier.com/amdreier-com)). The content is a work-in-progress, but there are a few nice frontend animation features, featuring a few animations and a responsive deisgn.

[romaetplus.amdreier.com](https://romaetplus.amdreier.com) displays some of my backend skills. It implements login, logout, user registration, integration with a database, and calls to an API.
The main purpose of this project is to act as a login system for my Minecraft server (also hosted at romaetplus.amdreier.com). After you register, whenever you log in on the Web server, that computer communicates with my other computer, hosting my Minecraft server. It sends the connecting IP address to a node.js server hosted on that computer, which adds the IP address to the IP addresses allowed to access the Minecraft server. 
Some security concerns were taken into account. For example, the MySQL database hosting the user data doesn't save the password, but rather a hash computed from the password, which is checked against the submitted password whenever a user tries to log in, that way the acutal password is never stored on the database. I'm planning to add salts and a pepper to further increase this securtity. This is made easier to implement, as the password hashing is handeled by an internal node.js server which responds to requests from the main Web server. Another security feature is my use of .env files to hide sensitive internal passwords from this GitHub page.
This took me approximately 10 hours to complete, but much of that time was due to this being a personal project as well, so I took extra care, and I'm self-hosting this projects between two computers sitting in my house, so I had to implement a reliable way for these computers to communicate without exposing that to the outside internet, as well as figure out SSL certificates, using a CDN (Cloudflare), and general networking considerations (a little more about that on my portfolio page).

You can try this website out for yourself by going to romaetplus.amdreier.com, and trying out the Minecraft server. You should see that before signing in, you shouldn't be able to see that the server is running (No Connection), but after creating an account and signing in, you'll soon see the server up and running. (You still won't be able to join, however, as that requires you to be on the server whitelist)
