/****************************************************************************
*
Project Name: Smart Home Automation
Tyler Mayou
3/5/2025
ESP32 - WiFi/MQTT, LEDC, Speaker Control, Temp/Humidity Sensor
*
****************************************************************************/

//All necessary include files
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
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
#include "esp_http_client.h"
#include "freertos/queue.h"


#define LED_PIN GPIO_NUM_22
#define SPEAKER_PIN GPIO_NUM_19
#define RELAY_PIN GPIO_NUM_18


// Wi-Fi Credentials
#define WIFI_SSID "TAMU_IoT"
#define WIFI_PASS ""

//#define WIFI_SSID "MyOptimum c4c731"
//#define WIFI_PASS "78-ochre-5357"

#define HTTP_URL "https://absolutely-vocal-lionfish.ngrok-free.app/"


static const char *TAG = "ESP32";
//static esp_mqtt_client_handle_t client;




QueueHandle_t light_queue;

//Global variables for GPIO Interrupts
uint32_t duty;
uint32_t duty_bulb = 0;
uint32_t duty_speaker;
uint32_t volume;
uint32_t brightness;
int on_off_LED;
int on_off_light;
int light_select = 0;
int on_off_speaker;
static TimerHandle_t debounce_timer;
static bool gpio_event_pending = false;

int LED_status;
int Speaker_status;
int Relay_status;

//Variables for temperature sensor
int16_t temperature = 0;
int16_t humidity = 0;

// Wi-Fi Event Handler
static void wifi_event_handler(void *arg, esp_event_base_t event_base, int32_t event_id, void *event_data) {
    if (event_base == WIFI_EVENT && event_id == WIFI_EVENT_STA_START) 
    {
        esp_wifi_connect();
    } 
    else if (event_base == IP_EVENT && event_id == IP_EVENT_STA_GOT_IP) 
    {
        ESP_LOGI(TAG, "Connected to Wi-Fi!");
    } 
    else if (event_base == WIFI_EVENT && event_id == WIFI_EVENT_STA_DISCONNECTED) 
    {
        ESP_LOGI(TAG, "Disconnected. Reconnecting...");
        esp_wifi_connect();
    }
}

// Wi-Fi Initialization
void wifi_init() {
    ESP_ERROR_CHECK(nvs_flash_init());
    ESP_ERROR_CHECK(esp_netif_init());
    ESP_ERROR_CHECK(esp_event_loop_create_default());

    esp_netif_create_default_wifi_sta();
    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    ESP_ERROR_CHECK(esp_wifi_init(&cfg));

    esp_event_handler_instance_register(WIFI_EVENT, ESP_EVENT_ANY_ID, &wifi_event_handler, NULL, NULL);
    esp_event_handler_instance_register(IP_EVENT, IP_EVENT_STA_GOT_IP, &wifi_event_handler, NULL, NULL);

    wifi_config_t wifi_config = {
        .sta = {
            .ssid = WIFI_SSID,
            .password = WIFI_PASS,
        },
    };
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_STA, &wifi_config));
    ESP_ERROR_CHECK(esp_wifi_start());
}


void http_status_task(void *pvParameters) {
    //int light_status;
    while (1) {
        if (xQueueReceive(light_queue, &LED_status, portMAX_DELAY)) {
            char post_data[50];
            snprintf(post_data, sizeof(post_data), "{\"device\":\"light\",\"state\":%d}", LED_status);

            esp_http_client_config_t config = {
                .url = HTTP_URL,
                .method = HTTP_METHOD_POST,
            };

            esp_http_client_handle_t client = esp_http_client_init(&config);
            esp_http_client_set_post_field(client, post_data, strlen(post_data));
            esp_http_client_set_header(client, "Content-Type", "application/json");

            esp_err_t err = esp_http_client_perform(client);
            if (err == ESP_OK) {
                ESP_LOGI("HTTP", "Light status sent: %d", LED_status);
            } else {
                ESP_LOGE("HTTP", "Failed to send light status");
            }

            esp_http_client_cleanup(client);
        }
    }
}



/*
// MQTT Event Handler
static void mqtt_event_handler(void *handler_args, esp_event_base_t base, int32_t event_id, void *event_data) {
    esp_mqtt_event_handle_t event = event_data; //Cast the event data to the appropriate type
    client = event->client; //Obtain client from event
    
    //switch statement to handle different types of MQTT events
    switch (event_id) {
        case MQTT_EVENT_CONNECTED: //MQTT Connectivity
            ESP_LOGI(TAG, "Connected to MQTT Broker"); //Log Successful connection

            //Subscribe to topics
            esp_mqtt_client_subscribe(client, "esp32/led", 0); 
            esp_mqtt_client_subscribe(client, "esp32/relay", 0);
            esp_mqtt_client_subscribe(client, "esp32/speaker", 0);
            break;

        //Trggered when a message is received on a topic that the client is subscribed to
        case MQTT_EVENT_DATA:
            ESP_LOGI(TAG, "MQTT Data Received: %.*s", event->data_len, event->data); //Log received data

            //check which topic the received message is for
            if (strncmp(event->topic, "esp32/led", event->topic_len) == 0) {
                if (strncmp(event->data, "ON", event->data_len) == 0) {
                    gpio_set_level(LED_PIN, 1); //set LED on
                } else {
                    gpio_set_level(LED_PIN, 0); //set LED off
                }
            } else if (strncmp(event->topic, "esp32/relay", event->topic_len) == 0) {
                if (strncmp(event->data, "ON", event->data_len) == 0) {
                    gpio_set_level(RELAY_PIN, 1); //set relay on
                } else {
                    gpio_set_level(RELAY_PIN, 0); //set relay off
                }
            } else if (strncmp(event->topic, "esp32/speaker", event->topic_len) == 0) {
                if (strncmp(event->data, "ON", event->data_len) == 0) {
                    gpio_set_level(SPEAKER_PIN, 1); //set speaker on
                } else {
                    gpio_set_level(SPEAKER_PIN, 0); //set speaker off
                }
            }
            break;
        default:
            break;
    }
}

// MQTT Initialization
void mqtt_init() {
    //Configure MQTT client settings
    esp_mqtt_client_config_t mqtt_cfg = {
        .broker.address.uri = MQTT_BROKER,
    };
    client = esp_mqtt_client_init(&mqtt_cfg); //pass the configuration structure to initialize client
    //register client
    esp_mqtt_client_register_event(client, ESP_EVENT_ANY_ID, mqtt_event_handler, NULL);
    esp_mqtt_client_start(client); //start the MQTT client
}
*/



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
/*
void lightbulb_configuration() {
    //First configure the timer of the LEDC
    ledc_timer_config_t lightbulb_timer = {
      .speed_mode      = LEDC_LOW_SPEED_MODE,
      .timer_num       = LEDC_TIMER_2,
      .duty_resolution = LEDC_TIMER_8_BIT,
      .freq_hz         = 500,
      .clk_cfg         = LEDC_AUTO_CLK
    };
    ledc_timer_config(&lightbulb_timer);
  
    //Second configure the channel of the LEDC
    ledc_channel_config_t lightbulb_channel = {
      .speed_mode = LEDC_LOW_SPEED_MODE,
      .channel    = LEDC_CHANNEL_2,
      .timer_sel  = LEDC_TIMER_2,
      .intr_type  = LEDC_INTR_DISABLE,
      .gpio_num   = 18,
      .duty       = duty_bulb,
      .hpoint     = 0
    };
    ledc_channel_config(&lightbulb_channel);
}*/

//Configure the speaker control
void speaker_configuration() {
  //First configure the timer of the speaker control
  ledc_timer_config_t speaker_timer = {
    .speed_mode      = LEDC_LOW_SPEED_MODE,
    .timer_num       = LEDC_TIMER_1,
    .duty_resolution = LEDC_TIMER_12_BIT,
    .freq_hz         = 100,
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
        if (duty < 8000 && on_off_LED == 1 && !gpio_event_pending) {
            duty += 2000; //adjust duty cycle
            brightness += 25;

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //udate duty cycle

            gpio_event_pending = true;
            xTimerStartFromISR(debounce_timer, NULL); //start timer
        }
    }

    if (light_select == 1) {
        /*
        if (duty_bulb < 250 && on_off_light == 1 && !gpio_event_pending) {
            duty_bulb += 50; //adjust duty cycle
            brightness += 25;

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_2, duty_bulb); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_2); //udate duty cycle

            gpio_event_pending = true;
            xTimerStartFromISR(debounce_timer, NULL); //start timer
        }*/
    }
}

//brightness down interrupt
static void IRAM_ATTR brightness_down(void* arg) {

    if (light_select == 0) {
        if (duty > 2000 && duty <= 8000 && on_off_LED == 1 && !gpio_event_pending) {
            duty -= 2000; //adjust duty cycle
            brightness -= 25;

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

        LED_status = 0;

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }

    
    if (light_select == 1 && !gpio_event_pending) {
        /*
        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_2, 0); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_2); //update duty cycle
        on_off_light = 0;
        */

        gpio_set_level(GPIO_NUM_18, 1);
        //printf("Relay OFF\n");

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer

    }
}

//light on interrupt
static void IRAM_ATTR light_on(void* arg) {

    if (light_select == 0 && !gpio_event_pending) {
        //duty = 0 set it to 8000
        if(duty == 0)
        {
            duty = 8000;
            brightness = 100;
        }

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

        on_off_LED = 1;

        LED_status = 1;

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
 

    if (light_select == 1 && !gpio_event_pending) {
        /*
        //duty = 0 set it to 8000
        if(duty_bulb == 0)
        {
            duty_bulb = 250;
            //brightness = 100;
        }

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_2, duty_bulb); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_2); //update duty cycle
        on_off_light = 1;
        */

        gpio_set_level(GPIO_NUM_18, 0);

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

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

    if (duty_speaker < 100 && on_off_speaker == 1 && !gpio_event_pending) {

        duty_speaker += 10; //adjust duty cycle
        volume += 10;

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

        gpio_event_pending = true;
        xTimerStartFromISR(debounce_timer, NULL); //start timer
    }
}

//volume down interrupt
static void IRAM_ATTR volume_down(void* arg) {

    if (duty_speaker > 10 && duty_speaker <= 100 && on_off_speaker == 1 && !gpio_event_pending) {

        duty_speaker -= 10; //adjust duty cycle
        volume -= 10; 

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
}

//speaker on interrupt
static void IRAM_ATTR speaker_on(void* arg) {

    if (!gpio_event_pending) {
        //if duty_speaker is 0 then set to 100
        if (duty_speaker == 0)
        {
            duty_speaker = 100;
            volume = 100;
        }

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle
        on_off_speaker = 1;

        /*for (int i = 0; i < 26; i++) { //source code is messed up and needs to cycle 26 times to read data

            //if read data is OK
            if(dht_read_data(DHT_TYPE_DHT11, GPIO_NUM_5, &humidity, &temperature) == ESP_OK) {
              
              humidity = humidity / 10; //modify humidity value
              temperature = ((temperature / 10) * 1.8) + 32; //convert from C to F
              printf("Humidity: %d%%, Temperature: %d F\n", humidity, temperature); //print sensor values
            }
        }*/

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
    gpio_isr_handler_add(GPIO_NUM_25, speaker_on, NULL);
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

}

/**********************MAIN**********************/

void app_main(void)
{
    //Call temperature sensor function to obtain data
    dht();

    //Call LEDC configuration
    ledc_configuration();

    //lightbulb_configuration();

    //Call Speaker Configuration
    speaker_configuration();

    //Call GPIO configuration
    gpio_configuration();

    //Create task for interrupt timer
    debounce_timer = xTimerCreate("debounce _timer", pdMS_TO_TICKS(1000), pdFALSE, NULL, debounce_timer_callback);

    nvs_flash_init();
    wifi_init();

    uint8_t mac[6];
    
    esp_wifi_get_mac(WIFI_IF_STA, mac);

    ESP_LOGI("Mac Address", "MAC Address: %02x:%02x:%02x:%02x:%02x:%02x", mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);

    light_queue = xQueueCreate(10, sizeof(int)); // Queue can hold 10 integer messages

    xTaskCreate(&http_status_task, "http_status_task", 8192, NULL, 5, NULL);
}