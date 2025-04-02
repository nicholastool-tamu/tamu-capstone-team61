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
#include "esp_http_client.h"
#include "freertos/queue.h"
#include "cJSON.h"
#include "esp_netif.h"
#include "lwip/dns.h"
#include "lwip/netdb.h"
#include "sys/socket.h"
#include "esp_wifi_types.h"
#include "server_cert.h"
#include "mbedtls/ssl.h"


#define LED_PIN GPIO_NUM_22
#define SPEAKER_PIN GPIO_NUM_19
#define RELAY_PIN GPIO_NUM_18




// Wi-Fi Credentials
//#define WIFI_SSID "TAMU_IoT"
//#define WIFI_PASS ""

// Wi-Fi Credentials
//#define WIFI_SSID "ParkWest.SynergyWifi.com"
//#define WIFI_PASS "Synergy.203.280.2029"

#define WIFI_SSID "iPhone"
#define WIFI_PASS "Tooter123!"

//#define WIFI_SSID "MyOptimum c4c731"
//#define WIFI_PASS "78-ochre-5357"

//#define HTTP_URL "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=17"


//static const char *TAG = "ESP32";
//static esp_mqtt_client_handle_t client;




QueueHandle_t light_queue;

//Global variables for GPIO Interrupts
uint32_t duty;
uint32_t duty_bulb = 0;
uint32_t duty_speaker;
uint32_t volume;
//uint32_t brightness;
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

// Logging tag
static const char *TAG = "HTTP_MULTI_DEVICE";

// Device state variables
static bool led_on = false;
static int led_brightness = 0;   // 0 to 100 (increments of 25)
static bool speaker_on = false;
static int speaker_volume = 0;   // 0 to 100 (increments of 10)
static bool relay_on = false;

/*
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
*/

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
    // Recommended to use at least WPA2
    //.threshold.authmode = WIFI_AUTH_OPEN, // Allow open networks,
    },
    };
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_STA, &wifi_config));

    // Start Wi-Fi
    ESP_ERROR_CHECK(esp_wifi_start());
    ESP_LOGI(TAG, "wifi_init_sta finished.");
}


/*
esp_err_t client_event_get_handler(esp_http_client_event_handle_t evt)
{
    switch (evt->event_id)
    {
        case HTTP_EVENT_ON_DATA:
            printf("Received response: %.*s\n", evt->data_len, (char *)evt->data);

            char *data = strndup(evt->data, evt->data_len);

            sscanf(data, "relay=%d&led=%ld&speaker=%ld&", &Relay_status, &duty, &duty_speaker);

            gpio_set_level(GPIO_NUM_18, Relay_status);

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

            free(data);
            break;
        
        default:
            break;

    }
    return ESP_OK;

}
*/
/*
void set_custom_dns()
{
    esp_netif_dns_info_t dns_info;
    IP4_ADDR(&dns_info.ip.u_addr.ip4, 1, 1, 1, 1);
    dns_info.ip.type = IPADDR_TYPE_V4;
    esp_netif_set_dns_info(esp_netif_get_handle_from_ifkey("WIFI_STA_DEF"), ESP_NETIF_DNS_MAIN, &dns_info);
    ESP_LOGI("DNS", "Custom DNS set to 1.1.1.1");
}

void dns_test(void) {
    struct addrinfo hints;
    struct addrinfo *res;
    int err;
    memset(&hints, 0, sizeof(hints));
    hints.ai_family = AF_INET;  // IPv4
    hints.ai_socktype = SOCK_STREAM;
   
    err = getaddrinfo("www.google.com", NULL, &hints, &res);
    if (err != 0) {
        ESP_LOGE(TAG, "DNS lookup failed: %s", strerror(err));
    } else {
        ESP_LOGI(TAG, "DNS lookup succeeded");
        // Optionally, print the resolved IP
        char ipStr[INET_ADDRSTRLEN];
        struct sockaddr_in *addr = (struct sockaddr_in *)res->ai_addr;
        inet_ntop(AF_INET, &(addr->sin_addr), ipStr, INET_ADDRSTRLEN);
        ESP_LOGI(TAG, "Resolved IP: %s", ipStr);
        freeaddrinfo(res);
    }
}
*/
/*
esp_err_t client_event_get_handler(esp_http_client_event_handle_t evt)
{
    switch (evt->event_id)
    {
        case HTTP_EVENT_ON_DATA:
        {
            // Allocate a buffer for the complete response
            char *data = malloc(evt->data_len + 1);
            if (data == NULL) {
                ESP_LOGE(TAG, "Failed to allocate memory for response");
                break;
            }
            memcpy(data, evt->data, evt->data_len);
            data[evt->data_len] = '\0';

            ESP_LOGI(TAG, "Received response: %s", data);

            // Parse the JSON response
            cJSON *json = cJSON_Parse(data);
            if (json == NULL) {
                ESP_LOGE(TAG, "JSON parse error");
            } else {
                // Check success flag
                cJSON *successItem = cJSON_GetObjectItem(json, "success");
                if (successItem && successItem->valueint == 1) {
                    // Get the array of devices
                    cJSON *dataArray = cJSON_GetObjectItem(json, "data");
                    if (cJSON_IsArray(dataArray)) {
                        // Assume device 17 is the first (or only) device in the array.
                        cJSON *device = cJSON_GetArrayItem(dataArray, 0);
                        if (device) {
                            // Get the device_settings string
                            cJSON *deviceSettingsItem = cJSON_GetObjectItem(device, "device_settings");
                            if (deviceSettingsItem && cJSON_IsString(deviceSettingsItem)) {
                                // Parse the device_settings JSON string
                                cJSON *settingsJson = cJSON_Parse(deviceSettingsItem->valuestring);
                                if (settingsJson) {
                                    // Extract brightness value from settings
                                    cJSON *brightnessItem = cJSON_GetObjectItem(settingsJson, "brightness");
                                    if (brightnessItem && cJSON_IsNumber(brightnessItem)) {
                                        brightness = brightnessItem->valueint;
                                        // Map brightness (0-100) to 0-255 for PWM duty cycle
                                        duty = brightness * 255 / 100;
                                    }
                                    cJSON_Delete(settingsJson);
                                }
                            }
                        }
                    }
                } else {
                    ESP_LOGE(TAG, "Device retrieval not successful");
                }
                cJSON_Delete(json);
            }
            free(data);

            // Update hardware outputs
            // Update relay (assuming Relay_status is updated elsewhere)
            gpio_set_level(GPIO_NUM_18, Relay_status);
            // Update LED PWM using the calculated duty cycle
            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty);
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0);
            // Update speaker duty (if applicable)
            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker);
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1);
        }
        break;
       
        default:
            break;
    }
    return ESP_OK;
}

static void rest_get_device_states()
{
    esp_http_client_config_t config_get = {
        .url = "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=17",
        .host = "absolutely-vocal-lionfish.ngrok-free.app",
        .method = HTTP_METHOD_GET,
        .event_handler = client_event_get_handler,
        .skip_cert_common_name_check = true,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config_get);

    esp_http_client_perform(client);
    esp_http_client_cleanup(client);
}

static void rest_post_device_state(const char *device, int value)
{
    esp_http_client_config_t config_post = {
        .url = HTTP_URL,
        .method = HTTP_METHOD_POST,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config_post);
    
    char post_data[50]; 
    snprintf(post_data, sizeof(post_data), "%s=%d", device, value);

    esp_http_client_set_post_field(client, post_data, strlen(post_data));
    esp_http_client_set_header(client, "Content-Type", "application/json");

    esp_http_client_perform(client);
    esp_http_client_cleanup(client);
}


void http_polling_task(void *pvParameter)
{
    while (1)
    {
        rest_get_device_states();
        vTaskDelay(pdMS_TO_TICKS(5000)); // Poll every 5 seconds
    }
}
*/

/*
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
*/

/*
// HTTP GET task: Periodically fetches data from httpbin.org/get
void http_get_test_task(void *pvParameters)
{
    const char *url = "http://httpbin.org/get";
    while (1) {
        esp_http_client_config_t config = {
            .url = url,
            .timeout_ms = 5000,
        };
        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "Failed to initialize HTTP client");
            vTaskDelay(5000 / portTICK_PERIOD_MS);
            continue;
        }

        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            int content_length = esp_http_client_get_content_length(client);
            ESP_LOGI(TAG, "HTTP GET Status = %d, Content Length = %d", status_code, content_length);
            
            // Optionally, read and log the response body
            char buffer[512];
            int data_read = esp_http_client_read(client, buffer, sizeof(buffer) - 1);
            if (data_read >= 0) {
                buffer[data_read] = 0;
                ESP_LOGI(TAG, "Response: %s", buffer);
            }
        } else {
            ESP_LOGE(TAG, "HTTP GET request failed: %s", esp_err_to_name(err));
        }
        esp_http_client_cleanup(client);
        vTaskDelay(10000 / portTICK_PERIOD_MS);
    }
}

// HTTP POST task: Periodically sends a JSON payload to httpbin.org/post
void http_post_test_task(void *pvParameters)
{
    const char *url = "http://httpbin.org/post";
    // Example JSON payload to send
    const char *post_data = "{\"device\": \"ESP32\", \"command\": \"LED_ON\"}";
    
    while (1) {
        esp_http_client_config_t config = {
            .url = url,
            .timeout_ms = 5000,
        };
        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "Failed to initialize HTTP client");
            vTaskDelay(5000 / portTICK_PERIOD_MS);
            continue;
        }
        
        // Set HTTP method to POST and provide the JSON payload
        esp_http_client_set_method(client, HTTP_METHOD_POST);
        esp_http_client_set_post_field(client, post_data, strlen(post_data));
        esp_http_client_set_header(client, "Content-Type", "application/json");
        
        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            ESP_LOGI(TAG, "HTTP POST Status = %d", status_code);
            
            // Optionally, read and log the response body
            char buffer[512];
            int data_read = esp_http_client_read(client, buffer, sizeof(buffer) - 1);
            if (data_read >= 0) {
                buffer[data_read] = 0;
                ESP_LOGI(TAG, "Response: %s", buffer);
            }
        } else {
            ESP_LOGE(TAG, "HTTP POST request failed: %s", esp_err_to_name(err));
        }
        esp_http_client_cleanup(client);
        vTaskDelay(15000 / portTICK_PERIOD_MS);
    }
}
*/

const char* clean_hostname(const char* hostname) {
    while (*hostname == ':') {
        hostname++;
    }
    return hostname;
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


static esp_err_t _http_event_handler(esp_http_client_event_t *evt)
{
    switch (evt->event_id) {
        case HTTP_EVENT_ON_DATA:
            // Log the data chunk received.
            ESP_LOGI(TAG, "HTTP_EVENT_ON_DATA: Received %d bytes", evt->data_len);
            ESP_LOGI(TAG, "Chunk: %.*s", evt->data_len, (char *)evt->data);
            break;
        default:
            break;
    }
    return ESP_OK;
}

void http_post_sensor_data_task(void *pvParameters)
{
    const char *url = "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=20";    //"http://postman-echo.com/post"; // Test URL
    char post_data[256];

    while (1) {

        dht();

        /*
        // Build JSON payload
        snprintf(post_data, sizeof(post_data),
                 "{\"temperature\": %.2d, \"humidity\": %.2d}",
                 temperature, humidity);
        */


        snprintf(post_data, sizeof(post_data),
                 "{\"Temperature\": %.2d}",
                 temperature);


        // Configure HTTP client with our event handler
        esp_http_client_config_t config = {


            
            .url = url,
            .host = clean_hostname("absolutely-vocal-lionfish.ngrok-free.app"),
            .port = 443,
            .method = HTTP_METHOD_POST,
            .timeout_ms = 5000,
            .event_handler = _http_event_handler,
            .cert_pem = server_cert_pem,
            .skip_cert_common_name_check = true,
            .tls_version = ESP_HTTP_CLIENT_TLS_VER_TLS_1_2,
            



            //.skip_cert_common_name_check = true,
        };

        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "Failed to initialize HTTP client");
            vTaskDelay(5000 / portTICK_PERIOD_MS);
            continue;
        }


        // Configure for a POST request with JSON data
        esp_http_client_set_method(client, HTTP_METHOD_POST);
        esp_http_client_set_post_field(client, post_data, strlen(post_data));
        esp_http_client_set_header(client, "Content-Type", "application/json");

        // Perform the HTTP POST request
        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            ESP_LOGI(TAG, "POST sensor data, Status = %d", status_code);
        } else {
            ESP_LOGE(TAG, "POST sensor data failed: %s", esp_err_to_name(err));
        }

        esp_http_client_cleanup(client);
        // Wait 15 seconds before sending the next POST
        vTaskDelay(15000 / portTICK_PERIOD_MS);
    }
}

// ------------------------- HTTP GET Task -------------------------
// This task polls the server for commands and updates device states accordingly.
void http_get_commands_task(void *pvParameters)
{
    // Replace with your Pi's server URL for command retrieval
    const char *url = "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=20";
    char buffer[512];

    while (1) {
        esp_http_client_config_t config = {
            .url = url,
            .host = clean_hostname("absolutely-vocal-lionfish.ngrok-free.app"),
            .timeout_ms = 5000,
            .cert_pem = server_cert_pem,
        };

        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "GET: Failed to initialize HTTP client");
            vTaskDelay(5000 / portTICK_PERIOD_MS);
            continue;
        }

        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            ESP_LOGI(TAG, "GET commands, Status = %d", status_code);

            int content_length = esp_http_client_get_content_length(client);
            if (content_length > 0 && content_length < sizeof(buffer)) {
                int read_len = esp_http_client_read(client, buffer, sizeof(buffer)-1);
                if (read_len > 0) {
                    buffer[read_len] = '\0';
                    ESP_LOGI(TAG, "GET response: %s", buffer);

                    // Process the response to update device states.
                    // For simplicity, assume the response is a plain text command.
                    // For example, it might contain "LED_ON", "LED_OFF", etc.
                    if (strstr(buffer, "LED_ON") != NULL) {
                        led_on = true;
                        ESP_LOGI(TAG, "Setting LED ON");
                    } else if (strstr(buffer, "LED_OFF") != NULL) {
                        led_on = false;
                        ESP_LOGI(TAG, "Setting LED OFF");
                    }
                    if (strstr(buffer, "LED_BRIGHTNESS_UP") != NULL) {
                        led_brightness = (led_brightness < 100) ? led_brightness + 25 : 100;
                        ESP_LOGI(TAG, "Increasing LED brightness");
                    } else if (strstr(buffer, "LED_BRIGHTNESS_DOWN") != NULL) {
                        led_brightness = (led_brightness > 0) ? led_brightness - 25 : 0;
                        ESP_LOGI(TAG, "Decreasing LED brightness");
                    }
                    if (strstr(buffer, "SPEAKER_ON") != NULL) {
                        speaker_on = true;
                        ESP_LOGI(TAG, "Turning Speaker ON");
                    } else if (strstr(buffer, "SPEAKER_OFF") != NULL) {
                        speaker_on = false;
                        ESP_LOGI(TAG, "Turning Speaker OFF");
                    }
                    if (strstr(buffer, "SPEAKER_VOLUME_UP") != NULL) {
                        speaker_volume = (speaker_volume < 100) ? speaker_volume + 10 : 100;
                        ESP_LOGI(TAG, "Increasing Speaker volume");
                    } else if (strstr(buffer, "SPEAKER_VOLUME_DOWN") != NULL) {
                        speaker_volume = (speaker_volume > 0) ? speaker_volume - 10 : 0;
                        ESP_LOGI(TAG, "Decreasing Speaker volume");
                    }
                    if (strstr(buffer, "RELAY_ON") != NULL) {
                        relay_on = true;
                        ESP_LOGI(TAG, "Turning Relay ON");
                    } else if (strstr(buffer, "RELAY_OFF") != NULL) {
                        relay_on = false;
                        ESP_LOGI(TAG, "Turning Relay OFF");
                    }
                }
            }
        } else {
            ESP_LOGE(TAG, "GET commands failed: %s", esp_err_to_name(err));
        }

        esp_http_client_cleanup(client);
        // Poll every 3 seconds (adjust as needed)
        vTaskDelay(3000 / portTICK_PERIOD_MS);
    }
}

void post_device_status(void) {

    const char *url = "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=20";

    char post_data[256];

    while(1) {

    
        // Build JSON payload with the current device state
        snprintf(post_data, sizeof(post_data),
                "{\"led_on\": %s, \"led_brightness\": %d, "
                "\"speaker_on\": %s, \"speaker_volume\": %d, "
                "\"relay_on\": %s}",
                led_on ? "true" : "false", led_brightness,
                speaker_on ? "true" : "false", speaker_volume,
                relay_on ? "true" : "false");

        ESP_LOGI(TAG, "Posting device status: %s", post_data);

        esp_http_client_config_t config = {
            .url = url,
            .timeout_ms = 5000,
            .cert_pem = server_cert_pem,
        };

        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "Failed to initialize HTTP client");
            return;
        }

        // Set the request as a POST and set headers and payload
        esp_http_client_set_method(client, HTTP_METHOD_POST);
        esp_http_client_set_header(client, "Content-Type", "application/json");
        esp_http_client_set_post_field(client, post_data, strlen(post_data));

        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            ESP_LOGI(TAG, "POST device status, Status = %d", status_code);
        } else {
            ESP_LOGE(TAG, "POST device status failed: %s", esp_err_to_name(err));
        }

        esp_http_client_cleanup(client);
        // Poll every 3 seconds (adjust as needed)
        vTaskDelay(3000 / portTICK_PERIOD_MS);
    }
}

void http_post_led_status_task(void *pvParameters)
{
    const char *led_url = "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=1";
    char post_data[128];
    while (1) {
        // Build JSON payload for LED status
        snprintf(post_data, sizeof(post_data),
                 "{\"led_on\": %s, \"led_brightness\": %d}",
                 led_on ? "true" : "false", led_brightness);

        esp_http_client_config_t config = {
            .url = led_url,
            .host = clean_hostname("absolutely-vocal-lionfish.ngrok-free.app"),
            .timeout_ms = 5000,
            .cert_pem = server_cert_pem,
        };
        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "LED: Failed to initialize HTTP client");
            vTaskDelay(10000 / portTICK_PERIOD_MS);
            continue;
        }

        esp_http_client_set_method(client, HTTP_METHOD_POST);
        esp_http_client_set_header(client, "Content-Type", "application/json");
        esp_http_client_set_post_field(client, post_data, strlen(post_data));

        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            ESP_LOGI(TAG, "LED: POST Status = %d", status_code);
        } else {
            ESP_LOGE(TAG, "LED: POST failed: %s", esp_err_to_name(err));
        }

        esp_http_client_cleanup(client);
        // Post every 3 seconds (adjust as needed)
        vTaskDelay(3000 / portTICK_PERIOD_MS);
    }
}

// ------------------------- Speaker POST Task -------------------------
void http_post_speaker_status_task(void *pvParameters)
{
    const char *speaker_url = "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=3";
    char post_data[128];
    while (1) {
        // Build JSON payload for Speaker status
        snprintf(post_data, sizeof(post_data),
                 "{\"speaker_on\": %s, \"speaker_volume\": %d}",
                 speaker_on ? "true" : "false", speaker_volume);

        esp_http_client_config_t config = {
            .url = speaker_url,
            .host = clean_hostname("absolutely-vocal-lionfish.ngrok-free.app"),
            .timeout_ms = 5000,
            .cert_pem = server_cert_pem,
        };
        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "Speaker: Failed to initialize HTTP client");
            vTaskDelay(10000 / portTICK_PERIOD_MS);
            continue;
        }

        esp_http_client_set_method(client, HTTP_METHOD_POST);
        esp_http_client_set_header(client, "Content-Type", "application/json");
        esp_http_client_set_post_field(client, post_data, strlen(post_data));

        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            ESP_LOGI(TAG, "Speaker: POST Status = %d", status_code);
        } else {
            ESP_LOGE(TAG, "Speaker: POST failed: %s", esp_err_to_name(err));
        }

        esp_http_client_cleanup(client);
        // Post every 3 seconds
        vTaskDelay(3000 / portTICK_PERIOD_MS);
    }
}

// ------------------------- Relay POST Task -------------------------
void http_post_relay_status_task(void *pvParameters)
{
    const char *relay_url = "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=19";
    char post_data[64];
    while (1) {
        // Build JSON payload for Relay status
        snprintf(post_data, sizeof(post_data),
                 "{\"relay_on\": %s}",
                 relay_on ? "true" : "false");

        esp_http_client_config_t config = {
            .url = relay_url,
            .host = clean_hostname("absolutely-vocal-lionfish.ngrok-free.app"),
            .timeout_ms = 5000,
            .cert_pem = server_cert_pem,
        };
        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "Relay: Failed to initialize HTTP client");
            vTaskDelay(10000 / portTICK_PERIOD_MS);
            continue;
        }

        esp_http_client_set_method(client, HTTP_METHOD_POST);
        esp_http_client_set_header(client, "Content-Type", "application/json");
        esp_http_client_set_post_field(client, post_data, strlen(post_data));

        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            ESP_LOGI(TAG, "Relay: POST Status = %d", status_code);
        } else {
            ESP_LOGE(TAG, "Relay: POST failed: %s", esp_err_to_name(err));
        }

        esp_http_client_cleanup(client);
        // Post every 3 seconds
        vTaskDelay(3000 / portTICK_PERIOD_MS);
    }
}

static esp_err_t _http_get_event_handler(esp_http_client_event_t *evt)
{
    switch (evt->event_id) {
        case HTTP_EVENT_ON_DATA:
            ESP_LOGI(TAG, "HTTP_EVENT_ON_DATA: Received %d bytes", evt->data_len);
            ESP_LOGI(TAG, "Chunk: %.*s", evt->data_len, (char *)evt->data);
            break;
        default:
            break;
    }
    return ESP_OK;
}

void http_get_task(void *pvParameters)
{
    // Change the URL to any endpoint you want to test. For example:
    const char *url = "https://absolutely-vocal-lionfish.ngrok-free.app/api/devices.php?device_id=20"; //"http://postman-echo.com/get";

    esp_http_client_config_t config = {
        .url = url,
        .timeout_ms = 5000,
        .event_handler = _http_get_event_handler,  // Attach our event handler
        .cert_pem = server_cert_pem,
        //.skip_cert_common_name_check = true,
    };

    while (1) {
        // Initialize the HTTP client with the configuration.
        esp_http_client_handle_t client = esp_http_client_init(&config);
        if (client == NULL) {
            ESP_LOGE(TAG, "Failed to initialize HTTP client");
            vTaskDelay(5000 / portTICK_PERIOD_MS);
            continue;
        }

        // The default method is GET, so no need to set it explicitly.
        esp_err_t err = esp_http_client_perform(client);
        if (err == ESP_OK) {
            int status_code = esp_http_client_get_status_code(client);
            ESP_LOGI(TAG, "GET request Status = %d", status_code);
        } else {
            ESP_LOGE(TAG, "GET request failed: %s", esp_err_to_name(err));
        }

        // Clean up the HTTP client.
        esp_http_client_cleanup(client);
        // Wait 15 seconds before sending the next GET request.
        vTaskDelay(15000 / portTICK_PERIOD_MS);
    }
}

/*
// Helper functions to update device settings
// For LED brightness, adjust in 25% increments (range 0 to 100)
void update_led_brightness(bool increase) {
    if (increase) {
        if (led_brightness < 100) {
            led_brightness += 25;
            if (led_brightness > 100) led_brightness = 100;
        }
    } else {
        if (led_brightness > 0) {
            led_brightness -= 25;
            if (led_brightness < 0) led_brightness = 0;
        }
    }
    ESP_LOGI(TAG, "LED brightness set to %d%%", led_brightness);
}

// For speaker volume, adjust in 10% increments (range 0 to 100)
void update_speaker_volume(bool increase) {
    if (increase) {
        if (speaker_volume < 100) {
            speaker_volume += 10;
            if (speaker_volume > 100) speaker_volume = 100;
        }
    } else {
        if (speaker_volume > 0) {
            speaker_volume -= 10;
            if (speaker_volume < 0) speaker_volume = 0;
        }
    }
    ESP_LOGI(TAG, "Speaker volume set to %d%%", speaker_volume);
}

// MQTT event handler callback
static esp_err_t mqtt_event_handler_cb(esp_mqtt_event_handle_t event) {
    esp_mqtt_client_handle_t client = event->client;

    // Copy the topic and payload into null-terminated strings.
    char topic[event->topic_len + 1];
    memcpy(topic, event->topic, event->topic_len);
    topic[event->topic_len] = '\0';

    char payload[event->data_len + 1];
    memcpy(payload, event->data, event->data_len);
    payload[event->data_len] = '\0';

    switch (event->event_id) {
        case MQTT_EVENT_CONNECTED:
            ESP_LOGI(TAG, "Connected to MQTT broker");
            // Subscribe to control topics for the devices
            esp_mqtt_client_subscribe(client, "control/led", 0);
            esp_mqtt_client_subscribe(client, "control/speaker", 0);
            esp_mqtt_client_subscribe(client, "control/relay", 0);
            break;

        case MQTT_EVENT_DISCONNECTED:
            ESP_LOGI(TAG, "Disconnected from MQTT broker");
            break;

        case MQTT_EVENT_SUBSCRIBED:
            ESP_LOGI(TAG, "Subscribed successfully, msg_id=%d", event->msg_id);
            break;

        case MQTT_EVENT_DATA:
            ESP_LOGI(TAG, "Received topic: %s | payload: %s", topic, payload);
            // Process control commands based on the topic
            if (strcmp(topic, "control/led") == 0) {
                if (strcmp(payload, "LED_ON") == 0) {
                    led_on = true;
                    ESP_LOGI(TAG, "LED turned ON");
                } else if (strcmp(payload, "LED_OFF") == 0) {
                    led_on = false;
                    ESP_LOGI(TAG, "LED turned OFF");
                } else if (strcmp(payload, "LED_BRIGHTNESS_UP") == 0) {
                    update_led_brightness(true);
                } else if (strcmp(payload, "LED_BRIGHTNESS_DOWN") == 0) {
                    update_led_brightness(false);
                }
            } else if (strcmp(topic, "control/speaker") == 0) {
                if (strcmp(payload, "SPEAKER_ON") == 0) {
                    speaker_on = true;
                    ESP_LOGI(TAG, "Speaker turned ON");
                } else if (strcmp(payload, "SPEAKER_OFF") == 0) {
                    speaker_on = false;
                    ESP_LOGI(TAG, "Speaker turned OFF");
                } else if (strcmp(payload, "SPEAKER_VOLUME_UP") == 0) {
                    update_speaker_volume(true);
                } else if (strcmp(payload, "SPEAKER_VOLUME_DOWN") == 0) {
                    update_speaker_volume(false);
                }
            } else if (strcmp(topic, "control/relay") == 0) {
                if (strcmp(payload, "RELAY_ON") == 0) {
                    relay_on = true;
                    ESP_LOGI(TAG, "Relay turned ON");
                } else if (strcmp(payload, "RELAY_OFF") == 0) {
                    relay_on = false;
                    ESP_LOGI(TAG, "Relay turned OFF");
                }
            }
            break;

        case MQTT_EVENT_ERROR:
            ESP_LOGE(TAG, "MQTT_EVENT_ERROR");
            break;

        default:
            break;
    }
    return ESP_OK;
}

// Wrapper for the MQTT event handler
static void mqtt_event_handler(void *handler_args, esp_event_base_t base,
                                 int32_t event_id, void *event_data) {
    mqtt_event_handler_cb(event_data);
}
*/



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
        if (duty < 8000 && led_on && !gpio_event_pending) {
            duty += 2000; //adjust duty cycle
            led_brightness += 25;

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //udate duty cycle

            //rest_post_device_state("led", brightness);

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
        if (duty > 2000 && duty <= 8000 && led_on && !gpio_event_pending) {
            duty -= 2000; //adjust duty cycle
            led_brightness -= 25;

            ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
            ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

            //rest_post_device_state("led", brightness);

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

        //rest_post_device_state("led", brightness);

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
        Relay_status = 1;

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
            led_brightness = 100;
        }

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0); //update duty cycle

        on_off_LED = 1;

        LED_status = 1;

        led_on = true;

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

        Relay_status = 0;

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

    if (duty_speaker < 100 && speaker_on == 1 && !gpio_event_pending) {

        duty_speaker += 10; //adjust duty cycle
        speaker_volume += 10;

        ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1, duty_speaker); //set duty cycle
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_1); //update duty cycle

        //rest_post_device_state("speaker", volume);

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

        //rest_post_device_state("speaker", volume);

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

    //rest_post_device_state("speaker", volume);
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

        /*for (int i = 0; i < 26; i++) { //source code is messed up and needs to cycle 26 times to read data

            //if read data is OK
            if(dht_read_data(DHT_TYPE_DHT11, GPIO_NUM_5, &humidity, &temperature) == ESP_OK) {
              
              humidity = humidity / 10; //modify humidity value
              temperature = ((temperature / 10) * 1.8) + 32; //convert from C to F
              printf("Humidity: %d%%, Temperature: %d F\n", humidity, temperature); //print sensor values
            }
        }*/

        //rest_post_device_state("speaker", volume);

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

}

/**********************MAIN**********************/

void app_main(void)
{
    // Set global log level to INFO so all our logs show up
    esp_log_level_set("*", ESP_LOG_INFO);

    // Initialize and start Wi-Fi
    wifi_init_sta();
    
    vTaskDelay(5000 / portTICK_PERIOD_MS);


    // Assuming Wi-Fi is already connected and you have a valid Wi-Fi netif handle.
    esp_netif_t *netif = esp_netif_get_handle_from_ifkey("WIFI_STA_DEF");
    esp_netif_dns_info_t dns_info;
    dns_info.ip.type = ESP_IPADDR_TYPE_V4;
    dns_info.ip.u_addr.ip4.addr = PP_HTONL(LWIP_MAKEU32(8,8,8,8));
    esp_netif_set_dns_info(netif, ESP_NETIF_DNS_MAIN, &dns_info);
    ESP_LOGI(TAG, "DNS set to 8.8.8.8");


    //Call temperature sensor function to obtain data
    //dht();

    //Call LEDC configuration
    ledc_configuration();

    //lightbulb_configuration();

    //Call Speaker Configuration
    speaker_configuration();

    //Call GPIO configuration
    gpio_configuration();

    //Create task for interrupt timer
    debounce_timer = xTimerCreate("debounce _timer", pdMS_TO_TICKS(1000), pdFALSE, NULL, debounce_timer_callback);



    vTaskDelay (pdMS_TO_TICKS(5000));

    /*
    // Configure MQTT client (update the URI to your Raspberry Pi's IP)
    const esp_mqtt_client_config_t mqtt_cfg = {
        .broker.address.uri = "mqtt://test.mosquitto.org:1883",  // Change this to your Raspberry Pi's IP address
        .credentials.client_id = "ESP32_Client_123",
    };
    

    // Initialize and start the MQTT client
    esp_mqtt_client_handle_t client = esp_mqtt_client_init(&mqtt_cfg);
    esp_mqtt_client_register_event(client, ESP_EVENT_ANY_ID, mqtt_event_handler, client);
    esp_mqtt_client_start(client);
    */

    uint8_t mac[6];
    
    esp_wifi_get_mac(WIFI_IF_STA, mac);

    ESP_LOGI("Mac Address", "MAC Address: %02x:%02x:%02x:%02x:%02x:%02x", mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);

    // Ensure Wi-Fi is connected before starting these tasks
    //xTaskCreate(&http_get_test_task, "http_get_test_task", 8192, NULL, 5, NULL);
    //xTaskCreate(&http_post_test_task, "http_post_test_task", 8192, NULL, 5, NULL);

    xTaskCreate(&http_post_sensor_data_task, "http_post_sensor_data_task", 8192, NULL, 5, NULL);
    xTaskCreate(&http_get_task, "http_get_task", 8192, NULL, 5, NULL);
    xTaskCreate(&http_get_commands_task, "http_get_commands_task", 8192, NULL, 5, NULL);
    xTaskCreate(&http_post_led_status_task, "http_post_led_status_task", 4096, NULL, 5, NULL);
    xTaskCreate(&http_post_speaker_status_task, "http_post_speaker_status_task", 4096, NULL, 5, NULL);
    xTaskCreate(&http_post_relay_status_task, "http_post_relay_status_task", 4096, NULL, 5, NULL);


    // Main loop – keep the task alive (you can add more app logic here)
    while (1) 
    {
       vTaskDelay(1000 / portTICK_PERIOD_MS);
    }

    //esp_log_level_set("mqtt_client", ESP_LOG_DEBUG);
}