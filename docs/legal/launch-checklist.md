# Legal & Enable Banking launch checklist

Complete before allowing public signups.

## Configuration

- [ ] Fill `LEGAL_*` and `APP_NAME` in production `.env`
- [ ] Set `APP_URL` to public HTTPS domain
- [ ] Lawyer review of `/privacy` and `/terms` content
- [ ] Privacy contact email (`LEGAL_EMAIL`) is monitored

## In-app verification

- [ ] Signup requires Terms + Privacy checkbox
- [ ] `/privacy` and `/terms` load without login
- [ ] Bank connect shows AIS consent screen before LHV redirect
- [ ] Settings lists connected banks with disconnect
- [ ] Data export includes `consents` array
- [ ] Account deletion removes all user tables including `account_consents`
- [ ] Disclaimer visible on Bank and LHV pages

## Enable Banking production

- [ ] Production application registered in Enable Banking dashboard
- [ ] `ENABLE_BANKING_REDIRECT_URL` matches dashboard (`https://yourdomain/bank/lhv/callback`)
- [ ] Privacy Policy URL submitted: `https://yourdomain/privacy`
- [ ] Application description states personal budgeting / AIS purpose
- [ ] Enable Banking attribution shown where required

## GDPR

- [ ] AKI (Andmekaitse Inspektsioon) referenced in Privacy Policy
- [ ] Sub-processors listed: Enable Banking, hosting, SMTP
- [ ] Export and delete flows tested manually
