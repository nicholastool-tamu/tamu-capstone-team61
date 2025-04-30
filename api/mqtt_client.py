#!/usr/bin/env python3
import time
import json
import paho.mqtt.client as mqtt

BROKER_ADDRESS = "localhost"
BROKER_PORT = 1883
TOPIC_TEMPERATURE = "device/thermo"

OUTPUT_FILE = "/var/www/backend/api/current_temperature.json"

def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print("Connected to MQTT broker.")
        client.subscribe(TOPIC_TEMPERATURE)
    else:
        print("Connection failed with code:", rc)

def on_message(client, userdata, msg):
    payload = msg.payload.decode("utf-8")
    print(f"Message on topic '{msg.topic}': {payload}")
    if msg.topic == TOPIC_TEMPERATURE and payload.lower().startswith("temperature:"):
        try:
            temp_str = payload.split("temperature:")[1].strip()
            temp_val = int(temp_str)
            print(f"Current Temperature: {temp_val}°F")
            # Update the output file with the latest temperature.
            output_data = {"temperature": temp_val}
            with open(OUTPUT_FILE, "w") as f:
                json.dump(output_data, f)
        except Exception as e:
            print("Error parsing temperature:", e)

client_id = f"pi_mqtt_client_{int(time.time())}"
client = mqtt.Client(client_id)
client.on_connect = on_connect
client.on_message = on_message

client.connect(BROKER_ADDRESS, BROKER_PORT, 60)
client.loop_forever()
