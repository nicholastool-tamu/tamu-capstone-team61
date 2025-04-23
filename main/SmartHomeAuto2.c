/****************************************************************************
*
Project Name: Smart Home Automation
Tyler Mayou
4/23/2025
ESP32 - WiFi/MQTT, LEDC, Speaker Control, Light/Relay, Temp/Humidity Sensor
*
****************************************************************************/

//All necessary include files
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdbool.h>
#include <inttypes.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/event_groups.h"
#include "esp_system.h"
#include "esp_log.h"
#include "sdkconfig.h"
#include "driver/gpio.h"
#include "driver/ledc.h"
#include "dht.h"
#include "freertos/timers.h"
#include "esp_wifi.h"
#include "freertos/queue.h"
#include "esp_event.h"
#include "nvs_flash.h"
#include "mqtt_client.h"
#include "esp_log.h"
#include "esp_netif.h"
#include "lwip/dns.h"
#include "lwip/netdb.h"
#include "esp_wifi_types.h"

#define LED_PIN GPIO_NUM_22
#define SPEAKER_PIN GPIO_NUM_19
#define RELAY_PIN GPIO_NUM_18

// Wi-Fi Credentials
#define WIFI_SSID "iPhone"
#define WIFI_PASS "Tooter123!"

esp_mqtt_client_handle_t client = NULL;

QueueHandle_t light_queue;

//Global variables for GPIO Interrupts
uint32_t duty;
uint32_t duty_bulb = 0;
uint16_t duty_speaker;
uint32_t volume;
//uint32_t brightness;
int on_off_LED;
int on_off_light;
int light_select = 0;
int on_off_speaker;
static TimerHandle_t debounce_timer;
static bool gpio_event_pending = false;

uint16_t newVolume;

int LED_status;
int Speaker_status;
int Relay_status;

//Variables for temperature sensor
int16_t temperature = 0;
int16_t humidity = 0;

//Logging tag
static const char *TAG = "MQTT_MULTI_DEVICE";

//Device state variables
static bool led_on = false;
static int led_brightness = 0;   // 0 to 100 (increments of 25)
static bool speaker_on = false;
static int speaker_volume = 0;   // 0 to 100 (increments of 10)
static bool relay_on = false;

//Note Frequencies for Mary Had a Little Lamb
#define NOTE_E4 330
#define NOTE_D4 294
#define NOTE_C4 262
#define NOTE_G4 392
#define NOTE_F4  349
#define NOTE_A4  440
#define NOTE_B4  494
#define NOTE_C5  523
#define NOTE_Bb4 466
#define REST     0

// Melody (notes) and durations (relative values)
int melody[] = {
    NOTE_E4, NOTE_D4, NOTE_C4, NOTE_D4, 
    NOTE_E4, NOTE_E4, NOTE_E4, 
    NOTE_D4, NOTE_D4, NOTE_D4, 
    NOTE_E4, NOTE_G4, NOTE_G4, 
    NOTE_E4, NOTE_D4, NOTE_C4, 
    NOTE_D4, NOTE_E4, NOTE_E4, NOTE_E4, 
    NOTE_E4, NOTE_D4, NOTE_D4, 
    NOTE_E4, NOTE_D4, NOTE_C4
};

int noteDurations[] = {
    4, 4, 4, 4,    // Quarter notes
    4, 4, 2,    
    4, 4, 2,
    4, 4, 2,
    4, 4, 4,
    4, 4, 4, 2,
    4, 4, 4,
    4, 4, 2
};

int numNotes = sizeof(melody) / sizeof(melody[0]);

// --- Melody Playback ---
void play_melody_task(void *arg)
{
    int note_index = 0;
    while (1) {
        if (speaker_on) {
            // Calculate note duration (ms)
            int noteDuration = 1000 / noteDurations[note_index];

            // Set the frequency for the current note
            ledc_set_freq(LEDC_LOW_SPEED_MODE, LEDC_TIMER_1, melody[note_index]);
            // Update the duty cycle (volume) using the current global setting
            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker);
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1);

            vTaskDelay(noteDuration / portTICK_PERIOD_MS);

            // Insert a short pause between notes (silence)
            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, 0);
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1);
            //10% pause relative to the note duration
            vTaskDelay((noteDuration * 0.1) / portTICK_PERIOD_MS);

            // Move to the next note; loop back to the beginning when finished
            note_index = (note_index + 1) % numNotes;
        } else {
            // Speaker is off, so pause the melody task without advancing the note index
            vTaskDelay(100 / portTICK_PERIOD_MS);
        }
    }   
}

// Event handler for Wi-Fi and IP events
static void wifi_event_handler(void *arg, esp_event_base_t event_base,
    int32_t event_id, void *event_data)
{
    // Log every event to see what happens
    ESP_LOGI(TAG, "Event received: %s, id: %ld", event_base, event_id);

    if (event_base == WIFI_EVENT) {
    switch (event_id) {
    case WIFI_EVENT_STA_START:
    ESP_LOGI(TAG, "Wi-Fi station started, attempting to connect...");
    esp_wifi_connect();
    break;

    case WIFI_EVENT_STA_DISCONNECTED:
    ESP_LOGI(TAG, "Disconnected from AP, reconnecting...");
    esp_wifi_connect();
    break;

    default:
    break;
    }
    } else if (event_base == IP_EVENT) {
    if (event_id == IP_EVENT_STA_GOT_IP) {
    // Extract the IP info from the event data and log it
    ip_event_got_ip_t *event = (ip_event_got_ip_t *) event_data;
    ESP_LOGI(TAG, "Got IP: " IPSTR, IP2STR(&event->ip_info.ip));
    ESP_LOGI(TAG, "Connected to Wi-Fi!");
    }
    }
}

// Initialize and start Wi-Fi in station mode
void wifi_init_sta(void)
{
    // Initialize NVS — it is used to store Wi-Fi calibration data, etc.
    esp_err_t ret = nvs_flash_init();
    if (ret == ESP_ERR_NVS_NO_FREE_PAGES ||
    ret == ESP_ERR_NVS_NEW_VERSION_FOUND) {
    ESP_ERROR_CHECK(nvs_flash_erase());
    ret = nvs_flash_init();
    }
    ESP_ERROR_CHECK(ret);

    // Initialize the TCP/IP stack and default event loop
    ESP_ERROR_CHECK(esp_netif_init());
    ESP_ERROR_CHECK(esp_event_loop_create_default());

    // Create the default Wi-Fi station
    esp_netif_create_default_wifi_sta();

    // Initialize the Wi-Fi driver with default configuration
    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    ESP_ERROR_CHECK(esp_wifi_init(&cfg));

    // Register event handlers for both Wi-Fi and IP events
    ESP_ERROR_CHECK(esp_event_handler_instance_register(WIFI_EVENT,
                                ESP_EVENT_ANY_ID,
                                &wifi_event_handler,
                                NULL,
                                NULL));
    ESP_ERROR_CHECK(esp_event_handler_instance_register(IP_EVENT,
                                IP_EVENT_STA_GOT_IP,
                                &wifi_event_handler,
                                NULL,
                                NULL));

    // Configure Wi-Fi connection settings
    wifi_config_t wifi_config = {
    .sta = {
    .ssid = WIFI_SSID,
    .password = WIFI_PASS,

    },
    };
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_STA, &wifi_config));

    // Start Wi-Fi
    ESP_ERROR_CHECK(esp_wifi_start());
    ESP_LOGI(TAG, "wifi_init_sta finished.");
}


//read temperature and humidity from sensor function
void dht() 
{

  for (int i = 0; i < 26; i++) { //source code is messed up and needs to cycle 26 times to read data
    //if read data is OK
    if(dht_read_data(DHT_TYPE_DHT11, GPIO_NUM_5, &humidity, &temperature) == ESP_OK) {
      
      humidity = humidity / 10; //modify humidity value
      temperature = ((temperature / 10) * 1.8) + 32; //convert from C to F
      printf("Humidity: %d%%, Temperature: %d F\n", humidity, temperature); //print sensor values
    }
  }
}

//Define data structures for MQTT messaging
typedef struct {
    char topic[128];
    char data[256];
} mqtt_message_t;

QueueHandle_t mqtt_queue;

//MQTT event handler for all topic and data
static void mqtt_event_handler(void *handler_args, esp_event_base_t base, int32_t event_id, void *event_data)
{
    ESP_LOGD(TAG, "Event dispatched from event loop base=%s, event_id=%" PRIi32 "", base, event_id);
    esp_mqtt_event_handle_t event = event_data;
    client = event->client;
    int msg_id;
    switch ((esp_mqtt_event_id_t)event_id) {
    case MQTT_EVENT_CONNECTED:
        ESP_LOGI(TAG, "MQTT_EVENT_CONNECTED");

        //subscribe to all topics
        msg_id = esp_mqtt_client_subscribe(client, "device/led", 0);
        ESP_LOGI(TAG, "sent subscribe successful, msg_id=%d", msg_id);

        msg_id = esp_mqtt_client_subscribe(client, "device/speaker", 0);
        ESP_LOGI(TAG, "sent subscribe successful, msg_id=%d", msg_id);

        msg_id = esp_mqtt_client_subscribe(client, "device/relay", 0);
        ESP_LOGI(TAG, "sent subscribe successful, msg_id=%d", msg_id);

        msg_id = esp_mqtt_client_subscribe(client, "device/thermoSet", 0);
        ESP_LOGI(TAG, "sent subscribe successful, msg_id=%d", msg_id);

        break;
    case MQTT_EVENT_DISCONNECTED:
        ESP_LOGI(TAG, "MQTT_EVENT_DISCONNECTED");
        break;

    case MQTT_EVENT_SUBSCRIBED:
        ESP_LOGI(TAG, "MQTT_EVENT_SUBSCRIBED, msg_id=%d", event->msg_id);
        break;
    case MQTT_EVENT_UNSUBSCRIBED:
        ESP_LOGI(TAG, "MQTT_EVENT_UNSUBSCRIBED, msg_id=%d", event->msg_id);
        break;
    case MQTT_EVENT_PUBLISHED:
        ESP_LOGI(TAG, "MQTT_EVENT_PUBLISHED, msg_id=%d", event->msg_id);
        break;
    case MQTT_EVENT_DATA:
        ESP_LOGI(TAG, "MQTT_EVENT_DATA");
        printf("TOPIC=%.*s\r\n", event->topic_len, event->topic);
        printf("DATA=%.*s\r\n", event->data_len, event->data);
        ESP_LOGI(TAG, "Received topic: %.*s", event->topic_len, event->topic);
        ESP_LOGI(TAG, "Received data: %.*s", event->data_len, event->data);
            //Process LED control commands
            if (strncmp(event->topic, "device/led", event->topic_len) == 0) {
                if (strncmp(event->data, "LIGHT_ON", event->data_len) == 0) {
                    led_on = true;
                    if(duty == 0)
                    {
                        duty = 4000;
                        led_brightness = 100;
                    }

                    ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
                    ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

                    ESP_LOGI(TAG, "LED turned ON");
                } else if (strncmp(event->data, "LIGHT_OFF", event->data_len) == 0) {
                    led_on = false;

                    ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, 0); //set duty cycle
                    ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

                    ESP_LOGI(TAG, "LED turned OFF");
                } else if (strncmp(event->data, "LED_BRIGHTNESS_UP", event->data_len) == 0) {
                    if (led_brightness < 100) {
                        led_brightness += 25;
                        if (led_brightness > 100) led_brightness = 100;

                        if (duty < 4000 && led_on) {
                            duty += 1000; //adjust duty cycle
                            //led_brightness += 25;
                
                            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
                            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //udate duty cycle
                        }
                    }
                    ESP_LOGI(TAG, "LED brightness increased to %d", led_brightness);
                } else if (strncmp(event->data, "LED_BRIGHTNESS_DOWN", event->data_len) == 0) {
                    if (led_brightness > 0) {
                        led_brightness -= 25;
                        if (led_brightness < 0) led_brightness = 0;

                        if (duty > 1000 && duty <= 4000 && led_on) {
                            duty -= 1000; //adjust duty cycle
                            //led_brightness -= 25;
                
                            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
                            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle
                        }
                
                    }
                    ESP_LOGI(TAG, "LED brightness decreased to %d", led_brightness);
                }
            }
            //Process speaker control commands
            if (strncmp(event->topic, "device/speaker", event->topic_len) == 0) {
                if (strncmp(event->data, "SPEAKER_ON", event->data_len) == 0) {
                    speaker_on = true;

                    if (duty_speaker == 0)
                    {
                        duty_speaker = 30;
                        speaker_volume= 50;
                    }

                    ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                    ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                    ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);
                    ESP_LOGI(TAG, "Speaker turned ON");
                } else if (strncmp(event->data, "SPEAKER_OFF", event->data_len) == 0) {
                    speaker_on = false;

                    ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, 0); //set duty cycle
                    ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle
                    
                    ESP_LOGI(TAG, "Speaker turned OFF");
                } else if (strncmp(event->data, "SPEAKER_VOLUME:10", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 10;

                        duty_speaker = 2;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:100", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 100;

                        duty_speaker = 40;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);                        
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:90", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 90;
                        duty_speaker = 32;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker); 
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:80", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 80;

                        duty_speaker = 27;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:70", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 70;

                        duty_speaker = 22;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);                       
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:60", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 60;

                        duty_speaker = 17;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);                     
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:50", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 50;

                        duty_speaker = 13;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);                        
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:40", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 40;

                        duty_speaker = 9;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);
                    } 
                } else if (strncmp(event->data, "SPEAKER_VOLUME:30", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 30;

                        duty_speaker = 6;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);                       
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:20", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 20;

                        duty_speaker = 4;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);                    
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);                     
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:10", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 10;

                        duty_speaker = 2;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);                       
                    }
                } else if (strncmp(event->data, "SPEAKER_VOLUME:0", event->data_len) == 0) {
                    if (speaker_on) {
                        newVolume = 0;

                        duty_speaker = 0;

                        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
                        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

                        ESP_LOGI(TAG, "Setting volume to %d%%", newVolume);
                        ESP_LOGI(TAG, "duty_speaker is %d", duty_speaker);                      
                    }
                } 
            }
            //Process relay/light bulb control commands
            if (strncmp(event->topic, "device/relay", event->topic_len) == 0) {
                if (strncmp(event->data, "LIGHT_ON", event->data_len) == 0) {
                    relay_on = true;

                    gpio_set_level(GPIO_NUM_18, 0);

                    ESP_LOGI(TAG, "Relay turned ON");
                } else if (strncmp(event->data, "LIGHT_OFF", event->data_len) == 0) {
                    relay_on = false;

                    gpio_set_level(GPIO_NUM_18, 1);
                    
                    ESP_LOGI(TAG, "Relay turned OFF");
                }
            }
        break;
    case MQTT_EVENT_ERROR:
        ESP_LOGI(TAG, "MQTT_EVENT_ERROR");
        if (event->error_handle->error_type == MQTT_ERROR_TYPE_TCP_TRANSPORT) {
            ESP_LOGI(TAG, "Last errno string (%s)", strerror(event->error_handle->esp_transport_sock_errno));
        }
        break;
    default:
        ESP_LOGI(TAG, "Other event id:%d", event->event_id);
        break;
    }
}

//Function to start up MQTT Connection
static void mqtt_app_start(void)
{
    //configure MQTT client
    esp_mqtt_client_config_t mqtt_cfg = {
        .broker.address.uri = "mqtt://3.tcp.ngrok.io:29613",
        .credentials.client_id = "ESP32_Client_123001",
    };
#if CONFIG_BROKER_URL_FROM_STDIN
    char line[128];

    if (strcmp(mqtt_cfg.broker.address.uri, "FROM_STDIN") == 0) {
        int count = 0;
        printf("Please enter url of mqtt broker\n");
        while (count < 128) {
            int c = fgetc(stdin);
            if (c == '\n') {
                line[count] = '\0';
                break;
            } else if (c > 0 && c < 127) {
                line[count] = c;
                ++count;
            }
            vTaskDelay(10 / portTICK_PERIOD_MS);
        }
        mqtt_cfg.broker.address.uri = line;
        printf("Broker url: %s\n", line);
    } else {
        ESP_LOGE(TAG, "Configuration mismatch: wrong broker url");
        abort();
    }
#endif /* CONFIG_BROKER_URL_FROM_STDIN */

    //intialize client
    client = esp_mqtt_client_init(&mqtt_cfg);
    esp_mqtt_client_register_event(client, ESP_EVENT_ANY_ID, mqtt_event_handler, NULL);
    esp_mqtt_client_start(client);
}

// Task to publish sensor data periodically
void publish_sensor_data_task(void *pvParameters)
{
    while (1) {
        dht(); //read temperture sensor
        char payload[128];
        snprintf(payload, sizeof(payload), "temperature:%d", temperature);
        int msg_id = esp_mqtt_client_publish(client, "device/thermo", payload, 0, 1, 0);
        ESP_LOGI(TAG, "Published sensor data: %s (msg_id=%d)", payload, msg_id);
        vTaskDelay(pdMS_TO_TICKS(5000)); // Publish every 5 seconds
    }
}



/******************************************************************************************************************************************* */

/*************Start of GPIO Interrupts****************/

//Configure the LEDC
void ledc_configuration() {
  //First configure the timer of the LEDC
  ledc_timer_config_t ledc_timer = {
    .speed_mode      = LEDC_LOW_SPEED_MODE,
    .timer_num       = LEDC_TIMER_0,
    .duty_resolution = LEDC_TIMER_13_BIT,
    .freq_hz         = 2000,
    .clk_cfg         = LEDC_AUTO_CLK
  };
  ledc_timer_config(&ledc_timer);

  //Second configure the channel of the LEDC
  ledc_channel_config_t ledc_channel = {
    .speed_mode = LEDC_LOW_SPEED_MODE,
    .channel    = LEDC_CHANNEL_0,
    .timer_sel  = LEDC_TIMER_0,
    .intr_type  = LEDC_INTR_DISABLE,
    .gpio_num   = 22,
    .duty       = duty,
    .hpoint     = 0
  };
  ledc_channel_config(&ledc_channel);
}


//Configure the speaker control
void speaker_configuration() {
  //First configure the timer of the speaker control
  ledc_timer_config_t speaker_timer = {
    .speed_mode      = LEDC_LOW_SPEED_MODE,
    .timer_num       = LEDC_TIMER_1,
    .duty_resolution = LEDC_TIMER_8_BIT,
    .freq_hz         = 440,
    .clk_cfg         = LEDC_AUTO_CLK
  };
  ledc_timer_config(&speaker_timer);

  //Second configure the channel of the speaker control
  ledc_channel_config_t speaker_channel = {
    .speed_mode = LEDC_LOW_SPEED_MODE,
    .channel    = LEDC_CHANNEL_1,
    .timer_sel  = LEDC_TIMER_1,
    .intr_type  = LEDC_INTR_DISABLE,
    .gpio_num   = 19,
    .duty       = duty_speaker,
    .hpoint     = 0
  };
  ledc_channel_config(&speaker_channel);
}

//brightness up interrupt
static void IRAM_ATTR brightness_up(void* arg) {

    if (light_select == 0) {
        if (duty < 8000 && led_on && !gpio_event_pending) {
            duty += 2000; //adjust duty cycle
            led_brightness += 25;

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //udate duty cycle

            gpio_event_pending = true;
            xTimerStartFromISR(debounce_timer, NULL); //start timer
        }
    }
}

//brightness down interrupt
static void IRAM_ATTR brightness_down(void* arg) {

    if (light_select == 0) {
        if (duty > 2000 && duty <= 8000 && led_on && !gpio_event_pending) {
            duty -= 2000; //adjust duty cycle
            led_brightness -= 25;

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

            gpio_event_pending = true;
            xTimerStartFromISR(debounce_timer, NULL); //start timer
        }
    }

    if (light_select == 1) {
        /*
        if (duty_bulb > 0 && duty_bulb <= 250 && on_off_light == 1 && !gpio_event_pending) {
            duty_bulb -= 50; //adjust duty cycle
            //brightness -= 25;

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_2, duty_bulb); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_2); //update duty cycle

            gpio_event_pending = true;
            xTimerStartFromISR(debounce_timer, NULL); //start timer
        }*/
    }
}

//light off interrupt
static void IRAM_ATTR light_off(void* arg) {

    if (light_select == 0 && !gpio_event_pending) {

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, 0); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle
        on_off_LED = 0;

        led_on = false;

        LED_status = 0;

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }

    
    if (light_select == 1 && !gpio_event_pending) {

        gpio_set_level(GPIO_NUM_18, 1);
        Relay_status = 1;

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer

    }
}

//light on interrupt
static void IRAM_ATTR light_on(void* arg) {

    if (light_select == 0 && !gpio_event_pending) {
        if(duty == 0)
        {
            duty = 8000;
            led_brightness = 100;
        }

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

        char payload[128] = "LED_ON";

        esp_mqtt_client_publish(client, "device/led", payload, 0, 1, 0);

        on_off_LED = 1;

        LED_status = 1;

        led_on = true;

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
 

    if (light_select == 1 && !gpio_event_pending) {

        gpio_set_level(GPIO_NUM_18, 0);

        Relay_status = 0;

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

//Interrupts if buttons were used
//switch light interrupt
static void IRAM_ATTR switch_light(void* arg) {

    if (light_select == 0) {
        light_select = 1;
    }
    else {
        light_select = 0;
    }

    gpio_event_pending = true;
    xTimerStartFromISR(debounce_timer, NULL); //start timer
}

//volume up interrupt
static void IRAM_ATTR volume_up(void* arg) {

    if (duty_speaker < 100 && speaker_on && !gpio_event_pending) {

        duty_speaker += 10; //adjust duty cycle
        speaker_volume += 10;

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

//volume down interrupt
static void IRAM_ATTR volume_down(void* arg) {

    if (duty_speaker > 10 && duty_speaker <= 100 && speaker_on == true && !gpio_event_pending) {

        duty_speaker -= 10; //adjust duty cycle
        speaker_volume -= 10; 

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

//speaker off interrupt
static void IRAM_ATTR speaker_off(void* arg) {

    ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, 0); //set duty cycle
    ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle
    on_off_speaker = 0;

    speaker_on = false;
}

//speaker on interrupt
static void IRAM_ATTR Speaker_on(void* arg) {

    if (!gpio_event_pending) {
        //if duty_speaker is 0 then set to 100
        if (duty_speaker == 0)
        {
            duty_speaker = 100;
            speaker_volume= 100;
        }

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle
        on_off_speaker = 1;

        speaker_on = true;

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }

}

//Timer for in between interrupts
void debounce_timer_callback(TimerHandle_t xTimer) {
    gpio_event_pending = false;
}

void gpio_configuration() {

    //Configure inputs
    gpio_set_direction(GPIO_NUM_25, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_26, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_32, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_33, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_27, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_14, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_13, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_23, GPIO_MODE_INPUT);
    gpio_set_direction(GPIO_NUM_21, GPIO_MODE_INPUT);

    //set all input gpios to pulldown mode
    gpio_set_pull_mode(GPIO_NUM_25, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_26, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_32, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_33, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_27, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_14, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_13, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_23, GPIO_PULLDOWN_ONLY);
    gpio_set_pull_mode(GPIO_NUM_21, GPIO_PULLDOWN_ONLY);

    //install interrupt service
    gpio_install_isr_service(ESP_INTR_FLAG_LEVEL3);

    //add light on interrupt D21 (Switch light)
    gpio_isr_handler_add(GPIO_NUM_21, switch_light, NULL);
    gpio_set_intr_type(GPIO_NUM_21, GPIO_INTR_POSEDGE);

    //add light on interrupt D23 (Volume Down)
    gpio_isr_handler_add(GPIO_NUM_23, volume_down, NULL);
    gpio_set_intr_type(GPIO_NUM_23, GPIO_INTR_POSEDGE);

    //add light off interrupt D32 (Volume Up)
    gpio_isr_handler_add(GPIO_NUM_32, volume_up, NULL);
    gpio_set_intr_type(GPIO_NUM_32, GPIO_INTR_POSEDGE);

    //add brightness up interrupt D33 (Speajer Off)
    gpio_isr_handler_add(GPIO_NUM_33, speaker_off, NULL);
    gpio_set_intr_type(GPIO_NUM_33, GPIO_INTR_POSEDGE);

    //add brightness down interrupt D25 (Speaker On)
    gpio_isr_handler_add(GPIO_NUM_25, Speaker_on, NULL);
    gpio_set_intr_type(GPIO_NUM_25, GPIO_INTR_POSEDGE);

    //add speaker on interrupt D26 (Light On)
    gpio_isr_handler_add(GPIO_NUM_26, light_on, NULL);
    gpio_set_intr_type(GPIO_NUM_26, GPIO_INTR_POSEDGE);

    //add speaker off interrupt D27 (light off)
    gpio_isr_handler_add(GPIO_NUM_27, light_off, NULL);
    gpio_set_intr_type(GPIO_NUM_27, GPIO_INTR_POSEDGE);

    //add volume up interrupt D14 (Brightness Up)
    gpio_isr_handler_add(GPIO_NUM_14, brightness_up, NULL);
    gpio_set_intr_type(GPIO_NUM_14, GPIO_INTR_POSEDGE);

    //add volume down interrupt D13 (Brightness Down)
    gpio_isr_handler_add(GPIO_NUM_13, brightness_down, NULL);
    gpio_set_intr_type(GPIO_NUM_13, GPIO_INTR_POSEDGE);


    gpio_config_t relay_control;
    relay_control.intr_type = GPIO_INTR_DISABLE; //Disable interrupt
    relay_control.pin_bit_mask = (1ULL << GPIO_NUM_18);
    relay_control.mode = GPIO_MODE_OUTPUT;
    relay_control.pull_up_en = GPIO_PULLUP_DISABLE;
    relay_control.pull_down_en = GPIO_PULLDOWN_DISABLE;
    gpio_config(&relay_control);

    gpio_set_level(GPIO_NUM_18, 1);
}

/**********************MAIN**********************/

void app_main(void)
{
    // Set global log level to INFO so all our logs show up
    esp_log_level_set("*", ESP_LOG_INFO);

    esp_log_level_set("mqtt_client", ESP_LOG_DEBUG);
    esp_log_level_set("esp-tls-mbedtls", ESP_LOG_DEBUG);

    // Initialize and start Wi-Fi
    wifi_init_sta();
    
    vTaskDelay(5000 / portTICK_PERIOD_MS);

    //Set DNS to 8.8.8.8
    esp_netif_t *netif = esp_netif_get_handle_from_ifkey("WIFI_STA_DEF");
    esp_netif_dns_info_t dns_info;
    dns_info.ip.type = ESP_IPADDR_TYPE_V4;
    dns_info.ip.u_addr.ip4.addr = PP_HTONL(LWIP_MAKEU32(8,8,8,8));
    esp_netif_set_dns_info(netif, ESP_NETIF_DNS_MAIN, &dns_info);
    ESP_LOGI(TAG, "DNS set to 8.8.8.8");

    //Call LEDC configuration
    ledc_configuration();

    //Call Speaker Configuration
    speaker_configuration();

    //Call GPIO configuration
    gpio_configuration();

    //Create task for interrupt timer
    debounce_timer = xTimerCreate("debounce _timer", pdMS_TO_TICKS(1000), pdFALSE, NULL, debounce_timer_callback);

    mqtt_app_start();  // Start MQTT client

    vTaskDelay(5000 / portTICK_PERIOD_MS);

    //Create a task to publish sensor data periodically
    xTaskCreate(&publish_sensor_data_task, "publish_sensor_data_task", 8192, NULL, 5, NULL);

    //Create the melody playback task
    xTaskCreate(play_melody_task, "play_melody_task", 8192, NULL, 5, NULL);

    // Main loop – keep the task alive (you can add more app logic here)
    while (1) 
    {
       vTaskDelay(1000 / portTICK_PERIOD_MS);
    }
}