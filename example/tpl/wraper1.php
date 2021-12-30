<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{lang}}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>{{mail.title}}</title>

    <style type="text/css">
        @media only screen and (min-device-width: 601px) {
            .content {
                width: 600px !important;
            }
        }

        body[yahoo] .class {
        }

        .button {
            text-align: center;
            font-size: 18px;
            font-family: sans-serif;
            font-weight: bold;
            padding: 0 30px 0 30px;
        }

        .button a {
            color: #ffffff !important;
            text-decoration: none;
        }

        .button a:hover {
            text-decoration: underline;
        }

        @media only screen and (max-width: 550px), screen and (max-device-width: 550px) {
            body[yahoo] .buttonwrapper {
                background-color: transparent !important;
            }

            body[yahoo] .button a {
                background-color: #e05443;
                padding: 15px 15px 13px !important;
                display: block !important;
            }
    </style>
</head>

<body yahoo bgcolor="#f6f8f1" style="margin: 0; padding: 0; min-width: 100%; background-color: #f6f8f1;">
{{subjectFix}}
<!--[if (gte mso 9)|(IE)]>
<table width="600" align="center" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td>
<![endif]-->

<table class="content" align="center" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px;">
    <!--Header-->
    <tr>
        <td bgcolor="#c7d8a7" style="padding: 20px;">
            {{head1}}
        </td>
    </tr>


    <!--ТЕЛО ПИСЬМА-->
    <tr>
        <td class="content" bgcolor="#ffffff" style="width: 100%; max-width: 600px; padding: 30px 30px 30px 30px; border-bottom: 1px solid #f2eeed;">
            {{content}}
        </td>
    </tr>

    <!--Footer-->
    <tr>
        <td class="footer" bgcolor="#44525f" style="padding: 20px 30px 15px 30px;">

            {{footer1}}
        </td>
    </tr>
</table>

<!--[if (gte mso 9)|(IE)]>
</td>
</tr>
</table>
<![endif]-->
</body>
</html>