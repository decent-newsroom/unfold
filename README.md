# Unfold

Unfold is a customizable framework for your Nostr-based magazine.

## Setup

### Clone the repository

```bash
git clone https://github.com/decent-newsroom/unfold.git
cd unfold
```

### Create the .env file

Copy the example file `.env.dist` and replace placeholders with your actual configuration.

If you have your own MySQL database, comment out the database service in `compose.yaml` and skip root password in `.env`. 
There are additional comments to that effect in the files.
