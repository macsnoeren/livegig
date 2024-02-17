#!/usr/bin/python3

import json

fs = open("songs.csv", "r")

songs = []
i = 0

line = fs.readline()
while line:
    record = line.split(",")
    r = {
        "id": i,
        "title": record[2],
        "artist": record[1],
        "bpm": record[5],
        "description": record[0],
    }
    songs.append(r)
    i = i + 1
    line = fs.readline()

fs.close() 


print(json.dumps(songs, indent=4))
