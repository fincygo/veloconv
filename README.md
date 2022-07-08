# veloconv
veloconv for sabrina project

URL: http://<localhost>/veloconv

Mikroservice POST JSON payloads same as commad parameters:
{
    "file":<IRAP csv file or ECS minor_sections csv file with path>,
    "options":
    {
        "m": <Minimum length of ECS segments in metres, default value m=200>,
        "x": <Maximum length of ECS segments in metres, default value x=5000>,
        "d": <Value is i2e or e2i, Defines the direction of the conversion>,
        "z": <Average height to be used as z in linestring during conversion to ECS>,
        "p": <Parameter for generalisation of route lines, p means the maximum divergence from the original polygone. Default value is 1 metre, if p=0 then no generalisation, all segments will be converted to the polyline>,
        "s": <Length of iRAP segments in metres, default is s=100>,
        "i": <ID in survey table, default is i=1>,
    }
}

Response is Acces Denied if the POST payload is corrupt.
Else:
Conversion is success:
{
    "result": true,
    "message": "OK",
}

Conversion failed:
{
    "result": false,
    "message": "ERROR: <command message>",
}