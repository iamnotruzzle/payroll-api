using System.Text.Json;
using Refit;

namespace Payroll.Mobile.Services;

public static class ApiError
{
    public static string Message(Exception exception)
    {
        if (exception is ApiException apiException)
        {
            if (!string.IsNullOrWhiteSpace(apiException.Content))
            {
                try
                {
                    using var document = JsonDocument.Parse(apiException.Content);
                    if (document.RootElement.TryGetProperty("message", out var message))
                    {
                        return message.GetString() ?? apiException.Message;
                    }
                }
                catch (JsonException)
                {
                    // Fall through to the HTTP status message.
                }
            }

            return apiException.StatusCode switch
            {
                System.Net.HttpStatusCode.Unauthorized => "Invalid credentials or session expired.",
                System.Net.HttpStatusCode.Forbidden => "This account cannot use the mobile portal.",
                _ => apiException.Message,
            };
        }

        return exception.Message;
    }
}
